<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiModelsDep;
use app\dep\Ai\AiRunsDep;
use app\dep\Ai\AiRunStepsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Ai\AiChatService;
use app\validate\Ai\AiChatValidate;
use Webman\Event\Event;
use Webman\RedisQueue\Client as RedisQueue;

/**
 * AI 对话模块
 * 负责业务编排：会话管理、消息存储、Run/Step 记录、事件触发
 * AI 调用逻辑（客户端创建、消息构建、API 调用）委托给 AiChatService
 */
class AiChatModule extends BaseModule
{
    /**
     * 取消流式输出（仅运行中的 Run 可取消，已完成的静默返回成功）
     */
    public function cancel($request): array
    {
        $param = $this->validate($request, AiChatValidate::cancel());
        $runId  = (int)$param['run_id'];
        $userId = (int)$request->userId;

        $run = $this->dep(AiRunsDep::class)->find($runId);
        self::throwIf(!$run || $run->user_id !== $userId, 'Run 不存在或无权访问');

        // 已完成的直接返回，不报错
        if ($run->run_status !== AiEnum::RUN_STATUS_RUNNING) {
            return self::success(['run_id' => $runId, 'status' => 'already_completed']);
        }

        $this->dep(AiRunsDep::class)->markCanceled($runId);

        Event::emit('ai.run.canceled', [
            'run_id'          => $runId,
            'user_id'         => $userId,
            'conversation_id' => $run->conversation_id,
        ]);

        return self::success(['run_id' => $runId, 'status' => 'canceled']);
    }


    /**
     * 发送消息并获取 AI 回复（非流式）
     */
    public function send($request): array
    {
        $userId    = $request->userId;
        $startTime = microtime(true);
        $param     = $this->validate($request, AiChatValidate::send());

        $prepared = $this->prepareChat($param, $userId);
        if ($prepared[1] !== 0) {
            return $prepared;
        }
        $ctx = $prepared[0];

        $requestId = AiChatService::generateRequestId();
        $runId     = $this->createRun($requestId, $userId, $ctx);

        $errorMsg = null;
        try {
            $result = AiChatService::chat($ctx['client'], $ctx['payload'], $ctx['config']);

            $assistantMessageId = $this->saveAssistantMessage(
                $ctx['conversationId'], $result['content'], $result['usage'],
                $ctx['modelCode'], $requestId, $result['raw']['id'] ?? null
            );

            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->dep(AiRunsDep::class)->markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'prompt_tokens'        => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens'    => $result['usage']['completion_tokens'] ?? null,
                'total_tokens'         => $result['usage']['total_tokens'] ?? null,
                'latency_ms'           => $latencyMs,
            ]);

            Event::emit('ai.run.completed', [
                'run_id'               => $runId,
                'user_id'              => $userId,
                'conversation_id'      => $ctx['conversationId'],
                'assistant_message_id' => $assistantMessageId,
                'total_tokens'         => $result['usage']['total_tokens'] ?? null,
                'latency_ms'           => $latencyMs,
            ]);
        } catch (\Throwable $e) {
            $errorMsg  = $e->getMessage();
            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->dep(AiRunsDep::class)->markFailed($runId, $errorMsg);

            Event::emit('ai.run.failed', [
                'run_id'          => $runId,
                'user_id'         => $userId,
                'conversation_id' => $ctx['conversationId'],
                'error_msg'       => $errorMsg,
                'latency_ms'      => $latencyMs,
            ]);
        }

        if ($errorMsg !== null) {
            self::throw("AI 调用失败: {$errorMsg}");
        }

        if ($ctx['isNew']) {
            $this->autoGenerateTitle($ctx, $param['content'], $userId);
        }

        return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId]);
    }

    /**
     * 发送消息并获取 AI 回复（流式 SSE）
     * 通过 onChunk 回调逐步推送：conversation → run → content → done/error/canceled
     */
    public function sendStream(array $param, int $userId, callable $onChunk): array
    {
        $startTime = microtime(true);
        $stepNo    = 0;

        $prepared = $this->prepareChat($param, $userId, $onChunk);
        if ($prepared[1] !== 0) {
            return $prepared;
        }
        $ctx = $prepared[0];

        $requestId = AiChatService::generateRequestId();
        $runId     = $this->createRun($requestId, $userId, $ctx, true);

        $onChunk('run', ['run_id' => $runId, 'request_id' => $requestId]);

        $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_PROMPT, [
            'messages_count' => \count($ctx['payload']['messages']),
            'model'          => $ctx['modelCode'],
        ]);

        $errorMsg           = null;
        $llmStepId          = null;
        $llmStart           = microtime(true);
        $assistantMessageId = null;

        try {
            $llmStepId = $this->dep(AiRunStepsDep::class)->add([
                'run_id'       => $runId,
                'step_no'      => ++$stepNo,
                'step_type'    => AiEnum::STEP_TYPE_LLM,
                'status'       => AiEnum::STEP_STATUS_SUCCESS,
                'payload_json' => json_encode(['model' => $ctx['modelCode'], 'stream' => true], JSON_UNESCAPED_UNICODE),
                'is_del'       => CommonEnum::NO,
            ]);

            $result = AiChatService::chatStream(
                $ctx['client'], $ctx['payload'], $ctx['config'],
                fn($delta) => $onChunk('content', ['delta' => $delta]),
                function () use ($runId) {
                    $run = $this->dep(AiRunsDep::class)->find($runId);
                    return $run && $run->run_status === AiEnum::RUN_STATUS_CANCELED;
                }
            );

            // 用户取消
            if (!empty($result['canceled'])) {
                $this->dep(AiRunStepsDep::class)->updateStepStatus(
                    $llmStepId, AiEnum::STEP_STATUS_FAIL, '用户取消',
                    (int)((microtime(true) - $llmStart) * 1000)
                );

                $canceledAssistantId = null;
                if (!empty($result['content'])) {
                    $canceledAssistantId = $this->saveAssistantMessage(
                        $ctx['conversationId'], $result['content'], $result['usage'] ?? [],
                        $ctx['modelCode'], $requestId, $result['request_id'] ?? null
                    );
                }

                $onChunk('canceled', [
                    'conversation_id'      => $ctx['conversationId'],
                    'run_id'               => $runId,
                    'user_message_id'      => $ctx['userMessageId'],
                    'assistant_message_id' => $canceledAssistantId,
                ]);

                return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId, 'canceled' => true]);
            }

            $this->dep(AiRunStepsDep::class)->updateStepStatus(
                $llmStepId, AiEnum::STEP_STATUS_SUCCESS, null,
                (int)((microtime(true) - $llmStart) * 1000)
            );

            $assistantMessageId = $this->saveAssistantMessage(
                $ctx['conversationId'], $result['content'], $result['usage'],
                $ctx['modelCode'], $requestId, $result['request_id'] ?? null
            );

            $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_FINALIZE, [
                'assistant_message_id' => $assistantMessageId,
                'prompt_tokens'        => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens'    => $result['usage']['completion_tokens'] ?? null,
                'total_tokens'         => $result['usage']['total_tokens'] ?? null,
            ]);

            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->dep(AiRunsDep::class)->markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'prompt_tokens'        => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens'    => $result['usage']['completion_tokens'] ?? null,
                'total_tokens'         => $result['usage']['total_tokens'] ?? null,
                'latency_ms'           => $latencyMs,
            ]);

            Event::emit('ai.run.completed', [
                'run_id'               => $runId,
                'user_id'              => $userId,
                'conversation_id'      => $ctx['conversationId'],
                'assistant_message_id' => $assistantMessageId,
                'total_tokens'         => $result['usage']['total_tokens'] ?? null,
                'latency_ms'           => $latencyMs,
            ]);
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            if ($llmStepId) {
                $this->dep(AiRunStepsDep::class)->updateStepStatus(
                    $llmStepId, AiEnum::STEP_STATUS_FAIL, $errorMsg,
                    (int)((microtime(true) - $llmStart) * 1000)
                );
            }
            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->dep(AiRunsDep::class)->markFailed($runId, $errorMsg);

            Event::emit('ai.run.failed', [
                'run_id'          => $runId,
                'user_id'         => $userId,
                'conversation_id' => $ctx['conversationId'],
                'error_msg'       => $errorMsg,
                'latency_ms'      => $latencyMs,
            ]);
        }

        if ($errorMsg !== null) {
            $onChunk('error', ['msg' => "AI 调用失败: {$errorMsg}"]);
            return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId, 'error' => true]);
        }

        if ($ctx['isNew']) {
            $this->autoGenerateTitle($ctx, $param['content'], $userId);
        }

        $onChunk('done', [
            'conversation_id'      => $ctx['conversationId'],
            'run_id'               => $runId,
            'user_message_id'      => $ctx['userMessageId'],
            'assistant_message_id' => $assistantMessageId,
        ]);

        return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId]);
    }


    // ==================== 私有方法 ====================

    /**
     * 准备对话上下文（校验会话/智能体/模型，创建用户消息，构建 payload）
     * 新会话时自动创建 conversation 记录
     */
    private function prepareChat(array $param, int $userId, ?callable $onChunk = null): array
    {
        $conversationId = $param['conversation_id'] ?? null;
        $agentId        = $param['agent_id'] ?? null;
        $content        = $param['content'];
        $maxHistory     = (int)($param['max_history'] ?? 20);
        $attachments    = $param['attachments'] ?? [];
        $isNew          = false;

        if (empty($conversationId)) {
            self::throwIf(empty($agentId), '会话ID为空时，智能体ID必填');
            $conversationId = $this->dep(AiConversationsDep::class)->add([
                'user_id'         => $userId,
                'agent_id'        => $agentId,
                'title'           => '',
                'last_message_at' => date('Y-m-d H:i:s'),
                'status'          => CommonEnum::YES,
                'is_del'          => CommonEnum::NO,
            ]);
            $isNew = true;
            if ($onChunk) {
                $onChunk('conversation', ['conversation_id' => $conversationId]);
            }
        } else {
            $conversation = $this->dep(AiConversationsDep::class)->getByUser((int)$conversationId, $userId);
            self::throwNotFound($conversation, '会话不存在');
            $agentId = $conversation->agent_id;
        }

        $agent = $this->dep(AiAgentsDep::class)->get((int)$agentId);
        self::throwIf(!$agent, '智能体不存在');
        self::throwIf($agent->status !== CommonEnum::YES, '智能体已禁用');

        $model = $this->dep(AiModelsDep::class)->get((int)$agent->model_id);
        self::throwIf(!$model, '模型不存在');
        self::throwIf($model->status !== CommonEnum::YES, '模型已禁用');

        [$client, $config, $error] = AiChatService::createClient($model);
        self::throwIf($error, $error ?? '创建客户端失败');

        $metaJson = !empty($attachments)
            ? json_encode(['attachments' => $attachments], JSON_UNESCAPED_UNICODE)
            : null;

        $userMessageId = $this->dep(AiMessagesDep::class)->add([
            'conversation_id' => $conversationId,
            'role'            => AiEnum::ROLE_USER,
            'content'         => $content,
            'meta_json'       => $metaJson,
            'is_del'          => CommonEnum::NO,
        ]);

        $modalities = $model->modalities ?? null;
        $messages   = AiChatService::buildMessages($agent, $conversationId, $maxHistory, $modalities);
        $payload    = AiChatService::buildPayload($agent, $model, $messages);

        return self::success([
            'conversationId' => $conversationId,
            'agentId'        => (int)$agentId,
            'userMessageId'  => $userMessageId,
            'isNew'          => $isNew,
            'client'         => $client,
            'payload'        => $payload,
            'config'         => $config,
            'modelCode'      => $model->model_code,
            'modalities'     => $modalities,
        ]);
    }

    /**
     * 创建 Run 记录并触发 ai.run.started 事件
     */
    private function createRun(string $requestId, int $userId, array $ctx, bool $isStream = false): int
    {
        $runId = $this->dep(AiRunsDep::class)->add([
            'request_id'      => $requestId,
            'user_id'         => $userId,
            'agent_id'        => $ctx['agentId'],
            'conversation_id' => $ctx['conversationId'],
            'user_message_id' => $ctx['userMessageId'],
            'run_status'      => AiEnum::RUN_STATUS_RUNNING,
            'model_snapshot'  => $ctx['modelCode'],
            'is_del'          => CommonEnum::NO,
        ]);

        Event::emit('ai.run.started', [
            'run_id'          => $runId,
            'user_id'         => $userId,
            'conversation_id' => $ctx['conversationId'],
            'agent_id'        => $ctx['agentId'],
            'model_code'      => $ctx['modelCode'],
            'is_stream'       => $isStream,
        ]);

        return $runId;
    }

    /**
     * 添加 Step 记录（通用快捷方法）
     */
    private function addStep(int $runId, int $stepNo, int $stepType, array $payload, int $latencyMs = 0): int
    {
        return $this->dep(AiRunStepsDep::class)->add([
            'run_id'       => $runId,
            'step_no'      => $stepNo,
            'step_type'    => $stepType,
            'status'       => AiEnum::STEP_STATUS_SUCCESS,
            'latency_ms'   => $latencyMs,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'is_del'       => CommonEnum::NO,
        ]);
    }

    /**
     * 保存 AI 助手回复消息并更新会话最后消息时间
     */
    private function saveAssistantMessage(
        int     $conversationId,
        string  $content,
        array   $usage,
        string  $modelCode,
        ?string $runRequestId = null,
        ?string $providerRequestId = null
    ): int {
        $metaJson = null;
        if ($runRequestId || $providerRequestId) {
            $meta = array_filter([
                'run_request_id'      => $runRequestId,
                'provider_request_id' => $providerRequestId,
            ]);
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        $messageId = $this->dep(AiMessagesDep::class)->add([
            'conversation_id' => $conversationId,
            'role'            => AiEnum::ROLE_ASSISTANT,
            'content'         => $content,
            'meta_json'       => $metaJson,
            'is_del'          => CommonEnum::NO,
        ]);

        $this->dep(AiConversationsDep::class)->updateLastMessageAt($conversationId);

        return $messageId;
    }

    /**
     * 异步生成会话标题（放入 Redis 队列）
     */
    private function autoGenerateTitle(array $ctx, string $userMessage, int $userId): void
    {
        RedisQueue::send('generate_conversation_title', [
            'conversation_id' => $ctx['conversationId'],
            'agent_id'        => $ctx['agentId'],
            'user_message'    => $userMessage,
            'user_id'         => $userId,
        ]);
    }
}
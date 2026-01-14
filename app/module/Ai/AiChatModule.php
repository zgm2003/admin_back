<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiModelsDep;
use app\dep\Ai\AiRunsDep;
use app\dep\Ai\AiRunStepsDep;
use app\enum\CommonEnum;
use app\enum\AiEnum;
use app\module\BaseModule;
use app\service\Ai\AiChatService;
use app\validate\Ai\AiChatValidate;
use RuntimeException;
use Webman\RedisQueue\Client as RedisQueue;
use Webman\Event\Event;

/**
 * AI 对话模块
 * 负责业务编排：会话管理、Run 记录、Step 记录
 * AI 调用逻辑委托给 AiChatService
 */
class AiChatModule extends BaseModule
{
    protected AiConversationsDep $conversationsDep;
    protected AiMessagesDep $messagesDep;
    protected AiAgentsDep $agentsDep;
    protected AiModelsDep $modelsDep;
    protected AiRunsDep $runsDep;
    protected AiRunStepsDep $stepsDep;
    protected AiChatService $chatService;

    public function __construct()
    {
        $this->conversationsDep = new AiConversationsDep();
        $this->messagesDep = new AiMessagesDep();
        $this->agentsDep = new AiAgentsDep();
        $this->modelsDep = new AiModelsDep();
        $this->runsDep = new AiRunsDep();
        $this->stepsDep = new AiRunStepsDep();
        $this->chatService = new AiChatService();
    }

    /**
     * 取消流式输出
     */
    public function cancel($request): array
    {
        $param = $this->validate($request, AiChatValidate::cancel());

        $runId = (int)$param['run_id'];
        $userId = (int)$request->userId;

        $run = $this->runsDep->find($runId);
        self::throwIf(!$run || $run->user_id !== $userId, 'Run 不存在或无权访问');

        // 只能取消运行中的 Run
        if ($run->run_status !== AiEnum::RUN_STATUS_RUNNING) {
            // 已完成的直接返回成功，不报错
            return self::success(['run_id' => $runId, 'status' => 'already_completed']);
        }

        // 更新 Run 状态为取消
        $this->runsDep->markCanceled($runId);

        // 触发取消事件
        Event::emit('ai.run.canceled', [
            'run_id' => $runId,
            'user_id' => $userId,
            'conversation_id' => $run->conversation_id,
        ]);

        return self::success(['run_id' => $runId, 'status' => 'canceled']);
    }

    /**
     * 发送消息并获取 AI 回复（非流式）
     */
    public function send($request): array
    {
        $userId = $request->userId;
        $startTime = microtime(true);

        $param = $this->validate($request, AiChatValidate::send());

        $prepared = $this->prepareChat($param, $userId);
        if ($prepared[1] !== 0) {
            return $prepared;
        }
        $ctx = $prepared[0];

        $requestId = $this->chatService->generateRequestId();
        $runId = $this->createRun($requestId, $userId, $ctx);

        $errorMsg = null;
        try {
            $result = $this->chatService->chat($ctx['client'], $ctx['payload'], $ctx['config']);

            $assistantMessageId = $this->saveAssistantMessage(
                $ctx['conversationId'], $result['content'], $result['usage'],
                $ctx['modelCode'], $requestId, $result['raw']['id'] ?? null
            );

            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->runsDep->markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
                'latency_ms' => $latencyMs,
            ]);

            Event::emit('ai.run.completed', [
                'run_id' => $runId,
                'user_id' => $userId,
                'conversation_id' => $ctx['conversationId'],
                'assistant_message_id' => $assistantMessageId,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
                'latency_ms' => $latencyMs,
            ]);
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->runsDep->markFailed($runId, $errorMsg);

            Event::emit('ai.run.failed', [
                'run_id' => $runId,
                'user_id' => $userId,
                'conversation_id' => $ctx['conversationId'],
                'error_msg' => $errorMsg,
                'latency_ms' => $latencyMs,
            ]);
        }

        if ($errorMsg !== null) {
            self::throw('AI 调用失败: ' . $errorMsg);
        }

        if ($ctx['isNew']) {
            $this->autoGenerateTitle($ctx, $param['content'], $userId);
        }

        return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId]);
    }

    /**
     * 发送消息并获取 AI 回复（流式 SSE）
     */
    public function sendStream(array $param, int $userId, callable $onChunk): array
    {
        $startTime = microtime(true);
        $stepNo = 0;

        $prepared = $this->prepareChat($param, $userId, $onChunk);
        if ($prepared[1] !== 0) {
            return $prepared;
        }
        $ctx = $prepared[0];

        $requestId = $this->chatService->generateRequestId();
        $runId = $this->createRun($requestId, $userId, $ctx, true);

        $onChunk('run', ['run_id' => $runId, 'request_id' => $requestId]);

        $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_PROMPT, [
            'messages_count' => count($ctx['payload']['messages']),
            'model' => $ctx['modelCode'],
        ]);

        $errorMsg = null;
        $llmStepId = null;
        $llmStart = microtime(true);
        $streamContent = '';

        try {
            $llmStepId = $this->stepsDep->add([
                'run_id' => $runId,
                'step_no' => ++$stepNo,
                'step_type' => AiEnum::STEP_TYPE_LLM,
                'status' => AiEnum::STEP_STATUS_SUCCESS,
                'payload_json' => json_encode(['model' => $ctx['modelCode'], 'stream' => true], JSON_UNESCAPED_UNICODE),
                'is_del' => CommonEnum::NO,
            ]);

            $result = $this->chatService->chatStream(
                $ctx['client'], $ctx['payload'], $ctx['config'],
                function ($delta) use ($onChunk, &$streamContent) {
                    $onChunk('content', ['delta' => $delta]);
                    $streamContent .= $delta;
                },
                function () use ($runId) {
                    // 检查是否已取消（通过数据库状态）
                    $run = $this->runsDep->find($runId);
                    return $run && $run->run_status === AiEnum::RUN_STATUS_CANCELED;
                }
            );

            // 如果被取消
            if (!empty($result['canceled'])) {
                $this->stepsDep->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_FAIL, '用户取消',
                    (int)((microtime(true) - $llmStart) * 1000));
                
                // 保存已生成的部分内容
                if (!empty($result['content'])) {
                    $this->saveAssistantMessage(
                        $ctx['conversationId'], $result['content'], $result['usage'] ?? [],
                        $ctx['modelCode'], $requestId, $result['request_id'] ?? null
                    );
                }
                
                $onChunk('canceled', ['conversation_id' => $ctx['conversationId'], 'run_id' => $runId]);
                return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId, 'canceled' => true]);
            }

            $this->stepsDep->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_SUCCESS, null,
                (int)((microtime(true) - $llmStart) * 1000));

            $assistantMessageId = $this->saveAssistantMessage(
                $ctx['conversationId'], $result['content'], $result['usage'],
                $ctx['modelCode'], $requestId, $result['request_id'] ?? null
            );

            $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_FINALIZE, [
                'assistant_message_id' => $assistantMessageId,
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
            ]);

            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->runsDep->markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
                'latency_ms' => $latencyMs,
            ]);

            Event::emit('ai.run.completed', [
                'run_id' => $runId,
                'user_id' => $userId,
                'conversation_id' => $ctx['conversationId'],
                'assistant_message_id' => $assistantMessageId,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
                'latency_ms' => $latencyMs,
            ]);
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            if ($llmStepId) {
                $this->stepsDep->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_FAIL, $errorMsg,
                    (int)((microtime(true) - $llmStart) * 1000));
            }
            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->runsDep->markFailed($runId, $errorMsg);

            Event::emit('ai.run.failed', [
                'run_id' => $runId,
                'user_id' => $userId,
                'conversation_id' => $ctx['conversationId'],
                'error_msg' => $errorMsg,
                'latency_ms' => $latencyMs,
            ]);
        }

        if ($errorMsg !== null) {
            self::throw('AI 调用失败: ' . $errorMsg);
        }

        if ($ctx['isNew']) {
            $this->autoGenerateTitle($ctx, $param['content'], $userId);
        }

        $onChunk('done', ['conversation_id' => $ctx['conversationId'], 'run_id' => $runId]);

        return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId]);
    }

    // ========== 私有方法 ==========

    private function prepareChat(array $param, int $userId, ?callable $onChunk = null): array
    {
        $conversationId = $param['conversation_id'] ?? null;
        $agentId = $param['agent_id'] ?? null;
        $content = $param['content'];
        $maxHistory = (int)($param['max_history'] ?? 20);
        $attachments = $param['attachments'] ?? [];
        $isNew = false;

        if (empty($conversationId)) {
            self::throwIf(empty($agentId), '会话ID为空时，智能体ID必填');
            $conversationId = $this->conversationsDep->add([
                'user_id' => $userId,
                'agent_id' => $agentId,
                'title' => '',
                'last_message_at' => date('Y-m-d H:i:s'),
                'status' => CommonEnum::YES,
                'is_del' => CommonEnum::NO,
            ]);
            $isNew = true;
            if ($onChunk) {
                $onChunk('conversation', ['conversation_id' => $conversationId]);
            }
        } else {
            $conversation = $this->conversationsDep->getByUser((int)$conversationId, $userId);
            self::throwNotFound($conversation, '会话不存在');
            $agentId = $conversation->agent_id;
        }

        $agent = $this->agentsDep->get((int)$agentId);
        self::throwIf(!$agent, '智能体不存在');
        self::throwIf($agent->status !== CommonEnum::YES, '智能体已禁用');

        $model = $this->modelsDep->get((int)$agent->model_id);
        self::throwIf(!$model, '模型不存在');
        self::throwIf($model->status !== CommonEnum::YES, '模型已禁用');

        [$client, $config, $error] = $this->chatService->createClient($model);
        self::throwIf($error, $error ?? '创建客户端失败');

        $metaJson = null;
        if (!empty($attachments)) {
            $metaJson = json_encode(['attachments' => $attachments], JSON_UNESCAPED_UNICODE);
        }

        $userMessageId = $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_USER,
            'content' => $content,
            'meta_json' => $metaJson,
            'is_del' => CommonEnum::NO,
        ]);

        $modalities = $model->modalities ?? null;
        $messages = $this->chatService->buildMessages($agent, $conversationId, $maxHistory, $modalities);
        $payload = $this->chatService->buildPayload($agent, $model, $messages);

        return self::success([
            'conversationId' => $conversationId,
            'agentId' => (int)$agentId,
            'userMessageId' => $userMessageId,
            'isNew' => $isNew,
            'client' => $client,
            'payload' => $payload,
            'config' => $config,
            'modelCode' => $model->model_code,
            'modalities' => $modalities,
        ]);
    }

    private function createRun(string $requestId, int $userId, array $ctx, bool $isStream = false): int
    {
        $runId = $this->runsDep->add([
            'request_id' => $requestId,
            'user_id' => $userId,
            'agent_id' => $ctx['agentId'],
            'conversation_id' => $ctx['conversationId'],
            'user_message_id' => $ctx['userMessageId'],
            'run_status' => AiEnum::RUN_STATUS_RUNNING,
            'model_snapshot' => $ctx['modelCode'],
            'is_del' => CommonEnum::NO,
        ]);

        Event::emit('ai.run.started', [
            'run_id' => $runId,
            'user_id' => $userId,
            'conversation_id' => $ctx['conversationId'],
            'agent_id' => $ctx['agentId'],
            'model_code' => $ctx['modelCode'],
            'is_stream' => $isStream,
        ]);

        return $runId;
    }

    private function addStep(int $runId, int $stepNo, int $stepType, array $payload, int $latencyMs = 0): int
    {
        return $this->stepsDep->add([
            'run_id' => $runId,
            'step_no' => $stepNo,
            'step_type' => $stepType,
            'status' => AiEnum::STEP_STATUS_SUCCESS,
            'latency_ms' => $latencyMs,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'is_del' => CommonEnum::NO,
        ]);
    }

    private function saveAssistantMessage(
        int $conversationId,
        string $content,
        array $usage,
        string $modelCode,
        ?string $runRequestId = null,
        ?string $providerRequestId = null
    ): int {
        $metaJson = null;
        if ($runRequestId || $providerRequestId) {
            $meta = array_filter([
                'run_request_id' => $runRequestId,
                'provider_request_id' => $providerRequestId,
            ]);
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        $messageId = $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_ASSISTANT,
            'content' => $content,
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'model_snapshot' => $modelCode,
            'meta_json' => $metaJson,
            'is_del' => CommonEnum::NO,
        ]);

        $this->conversationsDep->updateLastMessageAt($conversationId);

        return $messageId;
    }

    private function autoGenerateTitle(array $ctx, string $userMessage, int $userId): void
    {
        // 异步生成标题，放入 Redis 队列
        RedisQueue::send('generate_conversation_title', [
            'conversation_id' => $ctx['conversationId'],
            'agent_id' => $ctx['agentId'],
            'user_message' => $userMessage,
            'user_id' => $userId,
        ]);
    }
}

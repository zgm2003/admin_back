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
use app\service\Ai\AiStreamCacheService;
use app\validate\Ai\AiChatValidate;
use RuntimeException;
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

    // ========== 公开方法 ==========

    /**
     * 恢复/获取流式输出状态（用于会话切换后恢复）
     */
    public function resume(int $runId, int $userId): array
    {
        $run = $this->runsDep->find($runId);
        if (!$run || $run->user_id !== $userId) {
            return self::error('Run 不存在或无权访问');
        }

        $cache = AiStreamCacheService::get($runId);
        
        if ($cache) {
            return self::success([
                'run_id' => $runId,
                'conversation_id' => $run->conversation_id,
                'status' => $cache['status'],
                'content' => $cache['content'],
                'content_length' => strlen($cache['content']),
                'is_complete' => $cache['status'] !== AiStreamCacheService::STATUS_RUNNING,
                'can_subscribe' => $cache['status'] === AiStreamCacheService::STATUS_RUNNING,
                'error_msg' => $cache['error_msg'],
                'result' => $cache['result'],
            ]);
        }

        $statusMap = [
            AiEnum::RUN_STATUS_RUNNING => 'running',
            AiEnum::RUN_STATUS_SUCCESS => 'success',
            AiEnum::RUN_STATUS_FAIL => 'fail',
            AiEnum::RUN_STATUS_CANCELED => 'fail',
        ];
        $status = $statusMap[$run->run_status] ?? 'fail';

        $content = '';
        if ($run->assistant_message_id) {
            $message = $this->messagesDep->find($run->assistant_message_id);
            $content = $message->content ?? '';
        }

        return self::success([
            'run_id' => $runId,
            'conversation_id' => $run->conversation_id,
            'status' => $status,
            'content' => $content,
            'content_length' => strlen($content),
            'is_complete' => $status !== 'running',
            'can_subscribe' => false,
            'error_msg' => $run->error_msg,
            'result' => $run->assistant_message_id ? ['assistant_message_id' => $run->assistant_message_id] : null,
        ]);
    }

    /**
     * 续传流式输出（SSE）
     */
    public function resumeStream(int $runId, int $userId, int $offset, callable $onChunk): array
    {
        $run = $this->runsDep->find($runId);
        if (!$run || $run->user_id !== $userId) {
            return self::error('Run 不存在或无权访问');
        }

        if (!AiStreamCacheService::exists($runId)) {
            $cache = $this->resume($runId, $userId);
            if ($cache[1] === 0 && $cache[0]['is_complete']) {
                $onChunk('done', [
                    'conversation_id' => $run->conversation_id,
                    'run_id' => $runId,
                    'content' => $cache[0]['content'],
                ]);
                return self::success(['status' => 'completed']);
            }
            return self::error('流式缓存已过期');
        }

        $cache = AiStreamCacheService::get($runId);
        if ($cache && strlen($cache['content']) > $offset) {
            $onChunk('content', ['delta' => substr($cache['content'], $offset)]);
            $offset = strlen($cache['content']);
        }

        if ($cache && $cache['status'] !== AiStreamCacheService::STATUS_RUNNING) {
            if ($cache['status'] === AiStreamCacheService::STATUS_SUCCESS) {
                $onChunk('done', ['conversation_id' => $run->conversation_id, 'run_id' => $runId]);
            } else {
                $onChunk('error', ['msg' => $cache['error_msg'] ?? '未知错误']);
            }
            return self::success(['status' => $cache['status']]);
        }

        foreach (AiStreamCacheService::subscribe($runId, $offset) as $event) {
            switch ($event['type']) {
                case 'content':
                    $onChunk('content', ['delta' => $event['delta']]);
                    break;
                case 'done':
                    $onChunk('done', ['conversation_id' => $run->conversation_id, 'run_id' => $runId]);
                    return self::success(['status' => 'success']);
                case 'error':
                    $onChunk('error', ['msg' => $event['error_msg']]);
                    return self::success(['status' => 'fail']);
                case 'timeout':
                    return self::error('续传超时');
                case 'not_found':
                    return self::error('缓存已过期');
            }
        }

        return self::success(['status' => 'completed']);
    }

    /**
     * 发送消息并获取 AI 回复（非流式）
     */
    public function send($request): array
    {
        $userId = $request->userId;
        $startTime = microtime(true);

        try {
            $param = $this->validate($request, AiChatValidate::send());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

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

            // 触发完成事件
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

            // 触发失败事件
            Event::emit('ai.run.failed', [
                'run_id' => $runId,
                'user_id' => $userId,
                'conversation_id' => $ctx['conversationId'],
                'error_msg' => $errorMsg,
                'latency_ms' => $latencyMs,
            ]);
        }

        if ($errorMsg !== null) {
            return self::error('AI 调用失败: ' . $errorMsg);
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

        AiStreamCacheService::init($runId, [
            'user_id' => $userId,
            'conversation_id' => $ctx['conversationId'],
            'agent_id' => $ctx['agentId'],
            'model_code' => $ctx['modelCode'],
        ]);

        $onChunk('run', ['run_id' => $runId, 'request_id' => $requestId]);

        $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_PROMPT, [
            'messages_count' => count($ctx['payload']['messages']),
            'model' => $ctx['modelCode'],
        ]);

        $errorMsg = null;
        $llmStepId = null;
        $llmStart = microtime(true);

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
                function ($delta) use ($onChunk, $runId) {
                    $onChunk('content', ['delta' => $delta]);
                    AiStreamCacheService::append($runId, $delta);
                }
            );

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

            AiStreamCacheService::markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'conversation_id' => $ctx['conversationId'],
            ]);

            // 触发完成事件
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
        } finally {
            if ($errorMsg !== null) {
                $latencyMs = (int)((microtime(true) - $startTime) * 1000);
                $this->runsDep->markFailed($runId, $errorMsg);
                AiStreamCacheService::markFailed($runId, $errorMsg);

                // 触发失败事件
                Event::emit('ai.run.failed', [
                    'run_id' => $runId,
                    'user_id' => $userId,
                    'conversation_id' => $ctx['conversationId'],
                    'error_msg' => $errorMsg,
                    'latency_ms' => $latencyMs,
                ]);
            }
        }

        if ($errorMsg !== null) {
            return self::error('AI 调用失败: ' . $errorMsg);
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
            if (empty($agentId)) {
                return self::error('会话ID为空时，智能体ID必填');
            }
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
            if (!$conversation) {
                return self::error('会话不存在');
            }
            $agentId = $conversation->agent_id;
        }

        $agent = $this->agentsDep->get((int)$agentId);
        if (!$agent || $agent->status !== CommonEnum::YES) {
            return self::error($agent ? '智能体已禁用' : '智能体不存在');
        }

        $model = $this->modelsDep->get((int)$agent->model_id);
        if (!$model || $model->status !== CommonEnum::YES) {
            return self::error($model ? '模型已禁用' : '模型不存在');
        }

        [$client, $config, $error] = $this->chatService->createClient($model);
        if ($error) {
            return self::error($error);
        }

        // 构建用户消息的 meta_json（包含附件）
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

        // 获取模型的多模态能力
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

        // 触发 Run 开始事件
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
        $title = $this->chatService->generateTitle(
            $ctx['client'], $ctx['config'], $ctx['modelCode'], $userMessage
        );
        if ($title) {
            $this->conversationsDep->updateTitle($ctx['conversationId'], $title, $userId);
        }
    }
}

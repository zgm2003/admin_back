<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiAgentKnowledgeBasesDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiModelsDep;
use app\dep\Ai\AiRunsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Ai\AiChatService;
use app\service\Ai\AiRagService;
use app\service\Ai\AiRunEventPublisher;
use app\validate\Ai\AiChatValidate;
use Webman\Event\Event;
use Webman\RedisQueue\Redis as RedisQueue;

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

        $ctx = $this->prepareChat($param, $userId);

        $requestId = AiChatService::generateRequestId();
        $runId     = $this->createRun($requestId, $userId, $ctx);

        $errorMsg = null;
        try {
            $result = AiChatService::chat($ctx['agent'], $ctx['userContent'], $ctx['historyMessages']);

            $assistantMessageId = $this->saveAssistantMessage(
                $ctx['conversationId'], $result['content'],
                $requestId, $result['request_id'] ?? null
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
     * HTTP 连接只负责提交 run 并转发 run events，模型执行交给队列 worker。
     */
    public function sendStream(array $param, int $userId, callable $onChunk): array
    {
        $ctx = $this->prepareChat($param, $userId, $onChunk, false);
        $requestId = AiChatService::generateRequestId();
        $runId = $this->createRun($requestId, $userId, $ctx, true, [
            'runtime_overrides' => $this->runtimeOverrides($param),
        ]);

        $onChunk('run', ['run_id' => $runId, 'request_id' => $requestId]);

        try {
            $this->enqueueRun($runId, $userId);
        } catch (\Throwable $e) {
            $errorMsg = 'AI 运行投递失败: ' . $e->getMessage();
            $this->dep(AiRunsDep::class)->markFailed($runId, $errorMsg);
            Event::emit('ai.run.failed', [
                'run_id' => $runId,
                'user_id' => $userId,
                'conversation_id' => $ctx['conversationId'],
                'error_msg' => $errorMsg,
                'latency_ms' => 0,
            ]);
            $onChunk('error', ['msg' => $errorMsg]);

            return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId, 'error' => true]);
        }

        $this->relayRunEvents($runId, $onChunk);

        if ($ctx['isNew']) {
            $this->autoGenerateTitle($ctx, $param['content'], $userId);
        }

        return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId]);
    }

    /**
     * 启动 streamable run：短请求只负责创建 run 和投递队列，不占用 SSE 长连接。
     */
    public function startStream($request): array
    {
        $userId = (int)$request->userId;
        $param = $this->validate($request, AiChatValidate::send());

        $ctx = $this->prepareChat($param, $userId, null, false);
        $requestId = AiChatService::generateRequestId();
        $runId = $this->createRun($requestId, $userId, $ctx, true, [
            'runtime_overrides' => $this->runtimeOverrides($param),
            'transport' => 'streamable',
        ]);

        try {
            $this->enqueueRun($runId, $userId);
        } catch (\Throwable $e) {
            $errorMsg = 'AI 运行投递失败: ' . $e->getMessage();
            $this->dep(AiRunsDep::class)->markFailed($runId, $errorMsg);
            (new AiRunEventPublisher())->publishError($runId, $errorMsg);

            Event::emit('ai.run.failed', [
                'run_id' => $runId,
                'user_id' => $userId,
                'conversation_id' => $ctx['conversationId'],
                'error_msg' => $errorMsg,
                'latency_ms' => 0,
            ]);

            self::throw($errorMsg);
        }

        if ($ctx['isNew']) {
            $this->autoGenerateTitle($ctx, $param['content'], $userId);
        }

        return self::success([
            'conversation_id' => $ctx['conversationId'],
            'run_id' => $runId,
            'request_id' => $requestId,
            'user_message_id' => $ctx['userMessageId'],
            'agent_id' => $ctx['agentId'],
            'is_new' => $ctx['isNew'],
        ]);
    }

    /**
     * 拉取 streamable run events。这个接口必须是短请求，不能像 SSE 一样长时间占住 Windows 单 worker。
     */
    public function events($request): array
    {
        $userId = (int)$request->userId;
        $param = $this->validate($request, AiChatValidate::events());
        $runId = (int)$param['run_id'];
        $lastId = (string)($param['last_id'] ?? '0-0');

        $run = $this->dep(AiRunsDep::class)->find($runId);
        self::throwIf(!$run || (int)$run->user_id !== $userId || (int)$run->is_del !== CommonEnum::NO, 'Run 不存在或无权访问');

        $publisher = new AiRunEventPublisher();
        $this->failStalledRunIfNeeded($publisher, $run);

        $events = $publisher->read($runId, $lastId, null);
        if (empty($events) && !empty($param['timeout_ms'])) {
            usleep(min((int)$param['timeout_ms'], 50) * 1000);
            $events = $publisher->read($runId, $lastId, null);
        }

        if (!empty($events)) {
            $last = end($events);
            $lastId = (string)($last['id'] ?? $lastId);
        }

        $freshRun = $this->dep(AiRunsDep::class)->find($runId) ?: $run;
        $runStatus = (int)$freshRun->run_status;
        $terminalByStatus = in_array($runStatus, [
            AiEnum::RUN_STATUS_SUCCESS,
            AiEnum::RUN_STATUS_FAIL,
            AiEnum::RUN_STATUS_CANCELED,
        ], true);
        $terminalByEvent = false;
        foreach ($events as $event) {
            if (in_array($event['event'], ['done', 'error', 'canceled'], true)) {
                $terminalByEvent = true;
                break;
            }
        }

        return self::success([
            'events' => $events,
            'last_id' => $lastId,
            'run_status' => $runStatus,
            'terminal' => $terminalByStatus || $terminalByEvent,
            'error_msg' => (string)($freshRun->error_msg ?? ''),
        ]);
    }


    // ==================== 私有方法 ====================

    /**
     * 准备对话上下文（校验会话/智能体/模型，创建用户消息，构建历史）
     * 校验失败直接抛 BusinessException，调用方无需检查返回值
     *
     * @throws \app\exception\BusinessException
     */
    private function prepareChat(array $param, int $userId, ?callable $onChunk = null, bool $buildRuntime = true): array
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

        $ragChunks = [];
        $neuronAgent = null;
        $historyMessages = [];
        $userContent = $content;

        if ($buildRuntime) {
            $ragChunks = $this->retrieveRagChunks($agent, $content);
            if (!empty($ragChunks)) {
                $agent->system_prompt = AiRagService::buildAugmentedSystemPrompt((string)($agent->system_prompt ?? ''), $ragChunks);
            }

            // 运行时参数（用户在聊天界面调整的 temperature/max_tokens 等）
            $runtimeParams = array_filter([
                'temperature' => $param['temperature'] ?? null,
                'max_tokens'  => $param['max_tokens'] ?? null,
            ], fn($v) => $v !== null);

            [$neuronAgent, $error] = AiChatService::createAgent($model, $agent, $runtimeParams ?: null);
            self::throwIf($error, $error ?? '创建 Agent 失败');
        }

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

        if ($buildRuntime) {
            // 排除刚插入的用户消息，避免与 userContent 重复发送给 AI
            $historyMessages = AiChatService::buildMessages($agent, $conversationId, $maxHistory, $userMessageId);

            // 当前消息也需要构建多模态内容（与历史消息一致的格式）
            $userContent = AiChatService::buildMultimodalContent($content, $attachments);
        }

        return [
            'conversationId'  => $conversationId,
            'agentId'         => (int)$agentId,
            'userMessageId'   => $userMessageId,
            'isNew'           => $isNew,
            'agent'           => $neuronAgent,
            'userContent'     => $userContent,
            'historyMessages' => $historyMessages,
            'modelCode'       => $model->model_code,
            'ragChunks'       => $ragChunks,
        ];
    }

    private function retrieveRagChunks(object $agent, string $content): array
    {
        if (!$this->agentHasCapability($agent, AiEnum::CAPABILITY_RAG)) {
            return [];
        }

        $knowledgeBases = $this->dep(AiAgentKnowledgeBasesDep::class)->getActiveKnowledgeBasesByAgentId((int)$agent->id);
        if ($knowledgeBases->isEmpty()) {
            return [];
        }

        $knowledgeBaseIds = $knowledgeBases->pluck('id')->map(fn($id) => (int)$id)->toArray();
        $topK = (int)max($knowledgeBases->pluck('top_k')->map(fn($value) => (int)$value)->toArray() ?: [5]);
        $threshold = (float)min($knowledgeBases->pluck('score_threshold')->map(fn($value) => (float)$value)->toArray() ?: [0]);

        return AiRagService::retrieveFromKnowledgeBases($knowledgeBaseIds, $content, $topK, $threshold);
    }

    private function agentHasCapability(object $agent, string $capability): bool
    {
        $capabilities = $agent->capabilities_json ?? [];
        if (is_string($capabilities)) {
            $decoded = json_decode($capabilities, true);
            $capabilities = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($capabilities)) {
            $capabilities = [];
        }

        if (array_key_exists($capability, $capabilities)) {
            return (bool)$capabilities[$capability];
        }

        return match ($capability) {
            AiEnum::CAPABILITY_RAG => ($agent->mode ?? '') === AiEnum::MODE_RAG,
            AiEnum::CAPABILITY_TOOLS => ($agent->mode ?? '') === AiEnum::MODE_TOOL,
            AiEnum::CAPABILITY_WORKFLOW => ($agent->mode ?? '') === AiEnum::MODE_WORKFLOW,
            default => false,
        };
    }

    private function runtimeOverrides(array $param): array
    {
        return array_filter([
            'temperature' => $param['temperature'] ?? null,
            'max_tokens' => $param['max_tokens'] ?? null,
            'max_history' => $param['max_history'] ?? null,
        ], static fn($value) => $value !== null);
    }

    private function enqueueRun(int $runId, int $userId): void
    {
        RedisQueue::send('ai_run_execute', [
            'run_id' => $runId,
            'user_id' => $userId,
        ]);
    }

    private function failStalledRunIfNeeded(AiRunEventPublisher $publisher, object $run): void
    {
        if ((int)$run->run_status !== AiEnum::RUN_STATUS_RUNNING) {
            return;
        }
        if ($publisher->hasAnyEvent((int)$run->id)) {
            return;
        }

        $createdAt = strtotime((string)$run->created_at);
        if ($createdAt === false || time() - $createdAt < 20) {
            return;
        }

        $errorMsg = 'AI 队列未开始执行，请检查 redis-queue consumer_slow 是否运行';
        $this->dep(AiRunsDep::class)->markFailed((int)$run->id, $errorMsg);
        $publisher->publishError((int)$run->id, $errorMsg);
    }

    private function relayRunEvents(int $runId, callable $onChunk): void
    {
        $publisher = new AiRunEventPublisher();
        $lastId = '0-0';
        $deadline = time() + 3600;
        $startAt = time();
        $hasEvent = false;

        while (time() < $deadline) {
            if ($this->forwardRunEvents($publisher, $runId, $lastId, $onChunk, 1000, $hasEvent)) {
                return;
            }

            $run = $this->dep(AiRunsDep::class)->find($runId);
            if (!$run || (int)$run->run_status !== AiEnum::RUN_STATUS_RUNNING) {
                if ($this->waitForTerminalRunEvent($publisher, $runId, $lastId, $onChunk)) {
                    return;
                }

                if ($run && (int)$run->run_status === AiEnum::RUN_STATUS_CANCELED) {
                    $onChunk('canceled', [
                        'conversation_id' => (int)$run->conversation_id,
                        'run_id' => $runId,
                        'user_message_id' => (int)$run->user_message_id,
                        'assistant_message_id' => null,
                    ]);
                    return;
                }

                $onChunk('error', ['msg' => 'AI 运行已结束但未收到终止事件']);
                return;
            }

            if (!$hasEvent && time() - $startAt >= 20) {
                $errorMsg = 'AI 队列未开始执行，请检查 redis-queue consumer_slow 是否运行';
                $this->dep(AiRunsDep::class)->markFailed($runId, $errorMsg);
                $onChunk('error', ['msg' => $errorMsg]);
                return;
            }
        }

        $this->dep(AiRunsDep::class)->markFailed($runId, 'AI 运行事件等待超时');
        $onChunk('error', ['msg' => 'AI 运行事件等待超时']);
    }

    private function waitForTerminalRunEvent(
        AiRunEventPublisher $publisher,
        int $runId,
        string &$lastId,
        callable $onChunk
    ): bool {
        for ($i = 0; $i < 3; $i++) {
            $hasEvent = false;
            if ($this->forwardRunEvents($publisher, $runId, $lastId, $onChunk, 200, $hasEvent)) {
                return true;
            }
        }

        return false;
    }

    private function forwardRunEvents(
        AiRunEventPublisher $publisher,
        int $runId,
        string &$lastId,
        callable $onChunk,
        int $blockMs,
        bool &$hasEvent
    ): bool {
        foreach ($publisher->read($runId, $lastId, $blockMs) as $event) {
            $lastId = $event['id'];
            $hasEvent = true;
            $onChunk($event['event'], $event['data']);

            if (in_array($event['event'], ['done', 'error', 'canceled'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 创建 Run 记录并触发 ai.run.started 事件
     */
    private function createRun(string $requestId, int $userId, array $ctx, bool $isStream = false, array $meta = []): int
    {
        $runId = $this->dep(AiRunsDep::class)->add([
            'request_id'      => $requestId,
            'user_id'         => $userId,
            'agent_id'        => $ctx['agentId'],
            'conversation_id' => $ctx['conversationId'],
            'user_message_id' => $ctx['userMessageId'],
            'run_status'      => AiEnum::RUN_STATUS_RUNNING,
            'model_snapshot'  => $ctx['modelCode'],
            'meta_json'       => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
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
     * 保存 AI 助手回复消息并更新会话最后消息时间
     */
    private function saveAssistantMessage(
        int     $conversationId,
        string  $content,
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

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
use app\lib\Ai\Crypto\KeyVault;
use app\lib\Ai\AiClientFactory;
use app\lib\Ai\AiClientInterface;
use app\module\BaseModule;
use app\validate\Ai\AiChatValidate;
use RuntimeException;
use support\Log;

/**
 * AI 对话模块
 * 负责发送消息并获取 AI 回复（支持流式和非流式）
 */
class AiChatModule extends BaseModule
{
    protected AiConversationsDep $conversationsDep;
    protected AiMessagesDep $messagesDep;
    protected AiAgentsDep $agentsDep;
    protected AiModelsDep $modelsDep;
    protected AiRunsDep $runsDep;
    protected AiRunStepsDep $stepsDep;

    public function __construct()
    {
        $this->conversationsDep = new AiConversationsDep();
        $this->messagesDep = new AiMessagesDep();
        $this->agentsDep = new AiAgentsDep();
        $this->modelsDep = new AiModelsDep();
        $this->runsDep = new AiRunsDep();
        $this->stepsDep = new AiRunStepsDep();
    }

    // ========== 公开方法 ==========

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

        // 1. 准备对话上下文
        $prepared = $this->prepareChat($param, $userId);
        if ($prepared[1] !== 0) {
            return $prepared;
        }
        $ctx = $prepared[0];

        // 2. 创建 run 记录（状态=running）
        $requestId = $this->generateRequestId();
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

        // 3. 调用 AI API（非流式）
        $result = null;
        $errorMsg = null;
        try {
            $result = $ctx['client']->chatCompletions($ctx['payload'], $ctx['config']);

            // 4. 保存 AI 回复
            $assistantMessageId = $this->saveAssistantMessage(
                $ctx['conversationId'],
                $result['content'],
                $result['usage'],
                $ctx['modelCode'],
                $requestId,
                $result['raw']['id'] ?? null
            );

            // 5. 更新 run 状态为成功
            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->runsDep->markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
                'latency_ms' => $latencyMs,
            ]);
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $this->runsDep->markFailed($runId, $errorMsg);
        }

        if ($errorMsg !== null) {
            return self::error('AI 调用失败: ' . $errorMsg);
        }

        // 6. 新会话自动生成标题
        if ($ctx['isNew']) {
            $this->generateTitle(
                $ctx['conversationId'],
                $param['content'],
                $userId,
                $ctx['client'],
                $ctx['config'],
                $ctx['modelCode']
            );
        }

        return self::success([
            'conversation_id' => $ctx['conversationId'],
            'run_id' => $runId,
        ]);
    }

    /**
     * 发送消息并获取 AI 回复（流式 SSE）
     * @param array $param 已校验的参数
     * @param int $userId 用户 ID
     * @param callable $onChunk 回调函数 function(string $event, array $data)
     */
    public function sendStream(array $param, int $userId, callable $onChunk): array
    {
        $startTime = microtime(true);
        $stepNo = 0;

        // 1. 准备对话上下文
        $prepared = $this->prepareChat($param, $userId, $onChunk);
        if ($prepared[1] !== 0) {
            return $prepared;
        }
        $ctx = $prepared[0];

        // 2. 创建 run 记录（状态=running）
        $requestId = $this->generateRequestId();
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

        // 通知前端 run_id
        $onChunk('run', ['run_id' => $runId, 'request_id' => $requestId]);

        // === Step 1: 提示词构建 ===
        $promptStart = microtime(true);
        $this->stepsDep->add([
            'run_id' => $runId,
            'step_no' => ++$stepNo,
            'step_type' => AiEnum::STEP_TYPE_PROMPT,
            'status' => AiEnum::STEP_STATUS_SUCCESS,
            'latency_ms' => (int)((microtime(true) - $promptStart) * 1000),
            'payload_json' => json_encode([
                'messages_count' => count($ctx['payload']['messages']),
                'model' => $ctx['modelCode'],
            ], JSON_UNESCAPED_UNICODE),
            'is_del' => CommonEnum::NO,
        ]);

        // 3. 调用 AI API（流式）+ 保存结果，用 try/finally 保证 run 收尾
        $result = null;
        $errorMsg = null;
        $llmStepId = null;
        try {
            // === Step 2: LLM 调用 ===
            $llmStart = microtime(true);
            $llmStepId = $this->stepsDep->add([
                'run_id' => $runId,
                'step_no' => ++$stepNo,
                'step_type' => AiEnum::STEP_TYPE_LLM,
                'status' => AiEnum::STEP_STATUS_SUCCESS, // 默认成功，失败时更新
                'payload_json' => json_encode([
                    'model' => $ctx['modelCode'],
                    'stream' => true,
                ], JSON_UNESCAPED_UNICODE),
                'is_del' => CommonEnum::NO,
            ]);

            $result = $ctx['client']->chatCompletionsStream(
                $ctx['payload'],
                $ctx['config'],
                fn($delta, $chunk) => $onChunk('content', ['delta' => $delta])
            );

            // LLM 步骤完成，更新耗时和 payload
            $llmLatency = (int)((microtime(true) - $llmStart) * 1000);
            $this->stepsDep->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_SUCCESS, null, $llmLatency);

            // 4. 保存 AI 回复（带双 request_id）
            $assistantMessageId = $this->saveAssistantMessage(
                $ctx['conversationId'],
                $result['content'],
                $result['usage'],
                $ctx['modelCode'],
                $requestId,
                $result['request_id'] ?? null
            );

            // === Step 3: 最终化 ===
            $this->stepsDep->add([
                'run_id' => $runId,
                'step_no' => ++$stepNo,
                'step_type' => AiEnum::STEP_TYPE_FINALIZE,
                'status' => AiEnum::STEP_STATUS_SUCCESS,
                'latency_ms' => 0,
                'payload_json' => json_encode([
                    'assistant_message_id' => $assistantMessageId,
                    'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                    'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                    'total_tokens' => $result['usage']['total_tokens'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'is_del' => CommonEnum::NO,
            ]);

            // 5. 更新 run 状态为成功
            $latencyMs = (int)((microtime(true) - $startTime) * 1000);
            $this->runsDep->markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
                'latency_ms' => $latencyMs,
            ]);
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            // LLM 步骤失败，更新状态
            if ($llmStepId) {
                $llmLatency = (int)((microtime(true) - $llmStart) * 1000);
                $this->stepsDep->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_FAIL, $errorMsg, $llmLatency);
            }
        } finally {
            // 任何异常都要收尾 run 状态
            if ($errorMsg !== null) {
                $this->runsDep->markFailed($runId, $errorMsg);
            }
        }

        // 如果有错误，返回失败
        if ($errorMsg !== null) {
            return self::error('AI 调用失败: ' . $errorMsg);
        }

        // 6. 新会话自动生成标题
        if ($ctx['isNew']) {
            $this->generateTitle(
                $ctx['conversationId'],
                $param['content'],
                $userId,
                $ctx['client'],
                $ctx['config'],
                $ctx['modelCode']
            );
        }

        // 7. 通知前端流结束
        $onChunk('done', [
            'conversation_id' => $ctx['conversationId'],
            'run_id' => $runId,
        ]);

        return self::success(['conversation_id' => $ctx['conversationId'], 'run_id' => $runId]);
    }

    /**
     * 生成唯一的 request_id
     */
    private function generateRequestId(): string
    {
        return sprintf('%s-%s', date('YmdHis'), bin2hex(random_bytes(8)));
    }

    // ========== 私有方法：公共逻辑抽取 ==========

    /**
     * 准备对话上下文（会话、智能体、模型、消息组装）
     * @return array ['code' => 0, 'data' => [...]] 成功时返回上下文数据
     */
    private function prepareChat(array $param, int $userId, ?callable $onChunk = null): array
    {
        $conversationId = $param['conversation_id'] ?? null;
        $agentId = $param['agent_id'] ?? null;
        $content = $param['content'];
        $maxHistory = (int)($param['max_history'] ?? 20);
        $isNew = false;

        // 1. 处理会话
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
            // 流式时通知前端新会话 ID
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

        // 2. 查智能体
        $agent = $this->agentsDep->get((int)$agentId);
        if (!$agent) {
            return self::error('智能体不存在');
        }
        if ($agent->status !== CommonEnum::YES) {
            return self::error('智能体已禁用');
        }

        // 3. 查模型
        $model = $this->modelsDep->get((int)$agent->model_id);
        if (!$model) {
            return self::error('模型不存在');
        }
        if ($model->status !== CommonEnum::YES) {
            return self::error('模型已禁用');
        }

        // 4. 解密 API Key
        try {
            $apiKey = KeyVault::decrypt($model->api_key_enc ?? '');
        } catch (RuntimeException $e) {
            return self::error('API Key 解密失败: ' . $e->getMessage());
        }
        if (empty($apiKey)) {
            return self::error('模型未配置 API Key');
        }

        // 5. 创建 AI 客户端
        if (!AiClientFactory::isSupported($model->driver)) {
            return self::error('不支持的 AI 驱动: ' . $model->driver);
        }
        $client = AiClientFactory::create($model->driver);

        // 6. 写入用户消息
        $userMessageId = $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_USER,
            'content' => $content,
            'is_del' => CommonEnum::NO,
        ]);

        // 7. 组装 messages[]
        $messages = $this->buildMessages($agent, $conversationId, $maxHistory);

        // 8. 构建 payload
        $payload = $this->buildPayload($agent, $model, $messages);

        // 9. 返回上下文
        return self::success([
            'conversationId' => $conversationId,
            'agentId' => (int)$agentId,
            'userMessageId' => $userMessageId,
            'isNew' => $isNew,
            'client' => $client,
            'payload' => $payload,
            'config' => [
                'endpoint' => $model->endpoint ?? '',
                'apiKey' => $apiKey,
            ],
            'modelCode' => $model->model_code,
        ]);
    }

    /**
     * 组装发给 AI 的消息列表
     */
    private function buildMessages($agent, int $conversationId, int $maxHistory): array
    {
        $messages = [];

        // system prompt
        if (!empty($agent->system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $agent->system_prompt];
        }

        // 历史消息
        $history = $this->messagesDep->getRecentByConversationId($conversationId, $maxHistory * 2);
        foreach (array_reverse($history->toArray()) as $msg) {
            $roleStr = AiEnum::$roleArr[$msg['role']] ?? null;
            if ($roleStr) {
                $messages[] = ['role' => $roleStr, 'content' => $msg['content']];
            }
        }

        return $messages;
    }

    /**
     * 构建 AI 请求 payload
     */
    private function buildPayload($agent, $model, array $messages): array
    {
        // 模型默认参数
        $params = $model->default_params ?? [];

        // 智能体参数覆盖
        if ($agent->temperature !== null) {
            $params['temperature'] = (float)$agent->temperature;
        }
        if ($agent->max_tokens !== null) {
            $params['max_tokens'] = (int)$agent->max_tokens;
        }
        $params = array_merge($params, $agent->extra_params ?? []);

        // 最终 payload
        return array_merge($params, [
            'model' => $model->model_code,
            'messages' => $messages,
        ]);
    }

    /**
     * 保存 AI 回复消息
     * @param int $conversationId 会话 ID
     * @param string $content 消息内容
     * @param array $usage token 用量
     * @param string $modelCode 模型代码
     * @param string|null $runRequestId 我们生成的 request_id（用于追踪 run）
     * @param string|null $providerRequestId AI 供应商返回的 request_id
     * @return int 消息 ID
     */
    private function saveAssistantMessage(
        int $conversationId,
        string $content,
        array $usage,
        string $modelCode,
        ?string $runRequestId = null,
        ?string $providerRequestId = null
    ): int {
        // 构建 meta_json：同时存储我们的 request_id 和供应商的 request_id
        $metaJson = null;
        if ($runRequestId || $providerRequestId) {
            $meta = [];
            if ($runRequestId) {
                $meta['run_request_id'] = $runRequestId;
            }
            if ($providerRequestId) {
                $meta['provider_request_id'] = $providerRequestId;
            }
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

        // 更新会话的 last_message_at
        $this->conversationsDep->updateLastMessageAt($conversationId);

        return $messageId;
    }

    /**
     * 自动生成会话标题
     */
    private function generateTitle(
        int $conversationId,
        string $userMessage,
        int $userId,
        AiClientInterface $client,
        array $config,
        string $modelCode
    ): void {
        try {
            $prompt = "请根据以下用户消息，生成一个简短的会话标题（不超过20个字），直接返回标题文本，不要任何解释：\n\n" . $userMessage;

            $result = $client->chatCompletions([
                'model' => $modelCode,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 50,
                'temperature' => 0.7,
            ], $config);

            $title = trim($result['content'] ?? '');
            $title = trim($title, "\"'「」『』");
            if (mb_strlen($title) > 30) {
                $title = mb_substr($title, 0, 30) . '...';
            }

            if (!empty($title)) {
                $this->conversationsDep->updateTitle($conversationId, $title, $userId);
            }
        } catch (\Throwable $e) {
            \support\Log::warning('AI 生成标题失败', ['error' => $e->getMessage()]);
        }
    }
}

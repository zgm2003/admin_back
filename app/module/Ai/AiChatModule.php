<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiModelsDep;
use app\enum\CommonEnum;
use app\enum\AiEnum;
use app\lib\Ai\Crypto\KeyVault;
use app\lib\Ai\AiClientFactory;
use app\lib\Ai\AiClientInterface;
use app\module\BaseModule;
use app\validate\Ai\AiChatValidate;
use RuntimeException;

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

    public function __construct()
    {
        $this->conversationsDep = new AiConversationsDep();
        $this->messagesDep = new AiMessagesDep();
        $this->agentsDep = new AiAgentsDep();
        $this->modelsDep = new AiModelsDep();
    }

    // ========== 公开方法 ==========

    /**
     * 发送消息并获取 AI 回复（非流式）
     */
    public function send($request): array
    {
        $userId = $request->userId;

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

        // 2. 调用 AI API（非流式）
        try {
            $result = $ctx['client']->chatCompletions($ctx['payload'], $ctx['config']);
        } catch (RuntimeException $e) {
            return self::error('AI 调用失败: ' . $e->getMessage());
        }

        // 3. 保存 AI 回复
        $this->saveAssistantMessage(
            $ctx['conversationId'],
            $result['content'],
            $result['usage'],
            $ctx['modelCode'],
            $result['raw']['id'] ?? null
        );

        return self::success(['conversation_id' => $ctx['conversationId']]);
    }

    /**
     * 发送消息并获取 AI 回复（流式 SSE）
     * @param array $param 已校验的参数
     * @param int $userId 用户 ID
     * @param callable $onChunk 回调函数 function(string $event, array $data)
     */
    public function sendStream(array $param, int $userId, callable $onChunk): array
    {
        // 1. 准备对话上下文
        $prepared = $this->prepareChat($param, $userId, $onChunk);
        if ($prepared[1] !== 0) {
            return $prepared;
        }
        $ctx = $prepared[0];

        // 2. 调用 AI API（流式）
        try {
            $result = $ctx['client']->chatCompletionsStream(
                $ctx['payload'],
                $ctx['config'],
                fn($delta, $chunk) => $onChunk('content', ['delta' => $delta])
            );
        } catch (RuntimeException $e) {
            return self::error('AI 调用失败: ' . $e->getMessage());
        }

        // 3. 保存 AI 回复
        $this->saveAssistantMessage(
            $ctx['conversationId'],
            $result['content'],
            $result['usage'],
            $ctx['modelCode'],
            $result['request_id'] ?? null
        );

        // 4. 新会话自动生成标题
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

        // 5. 通知前端流结束
        $onChunk('done', ['conversation_id' => $ctx['conversationId']]);

        return self::success(['conversation_id' => $ctx['conversationId']]);
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
            $conversation = $this->conversationsDep->getById((int)$conversationId, $userId);
            if (!$conversation) {
                return self::error('会话不存在');
            }
            $agentId = $conversation->agent_id;
        }

        // 2. 查智能体
        $agent = $this->agentsDep->getById((int)$agentId);
        if (!$agent) {
            return self::error('智能体不存在');
        }
        if ($agent->status !== CommonEnum::YES) {
            return self::error('智能体已禁用');
        }

        // 3. 查模型
        $model = $this->modelsDep->getById((int)$agent->model_id);
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
        $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_USER,
            'content' => $content,
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ]);

        // 7. 组装 messages[]
        $messages = $this->buildMessages($agent, $conversationId, $maxHistory);

        // 8. 构建 payload
        $payload = $this->buildPayload($agent, $model, $messages);

        // 9. 返回上下文
        return self::success([
            'conversationId' => $conversationId,
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
     */
    private function saveAssistantMessage(
        int $conversationId,
        string $content,
        array $usage,
        string $modelCode,
        ?string $requestId
    ): void {
        $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_ASSISTANT,
            'content' => $content,
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'model_snapshot' => $modelCode,
            'meta_json' => $requestId ? json_encode(['request_id' => $requestId], JSON_UNESCAPED_UNICODE) : null,
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ]);

        // 更新会话的 last_message_at
        $this->conversationsDep->updateLastMessageAt($conversationId);
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

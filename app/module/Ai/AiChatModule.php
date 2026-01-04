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
use app\module\BaseModule;
use app\validate\Ai\AiChatValidate;
use RuntimeException;

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

    /**
     * 发送消息并获取 AI 回复（非流式）
     */
    public function send($request): array
    {
        // 1. 取 user_id
        $userId = $request->userId;

        // 2. 入参校验
        try {
            $param = $this->validate($request, AiChatValidate::send());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $conversationId = $param['conversation_id'] ?? null;
        $agentId = $param['agent_id'] ?? null;
        $content = $param['content'];
        $maxHistory = (int)($param['max_history'] ?? 20);

        // 3. 处理会话
        if (empty($conversationId)) {
            // conversation_id 为空时 agent_id 必填
            if (empty($agentId)) {
                return self::error('会话ID为空时，智能体ID必填');
            }
            // 创建新会话
            $conversationId = $this->conversationsDep->add([
                'user_id' => $userId,
                'agent_id' => $agentId,
                'title' => '',
                'last_message_at' => date('Y-m-d H:i:s'),
                'status' => CommonEnum::YES,
                'is_del' => CommonEnum::NO,
            ]);
        } else {
            // 校验会话存在、is_del=2、user_id=当前用户
            $conversation = $this->conversationsDep->getById((int)$conversationId, $userId);
            if (!$conversation) {
                return self::error('会话不存在');
            }
            $agentId = $conversation->agent_id;
        }

        // 4. 查 ai_agents
        $agent = $this->agentsDep->getById((int)$agentId);
        if (!$agent) {
            return self::error('智能体不存在');
        }
        if ($agent->status !== CommonEnum::YES) {
            return self::error('智能体已禁用');
        }

        $systemPrompt = $agent->system_prompt ?? '';
        $temperature = $agent->temperature;
        $maxTokens = $agent->max_tokens;
        $extraParams = $agent->extra_params ?? []; // 已被 Model cast 为数组
        $modelId = $agent->model_id;

        // 5. 查 ai_models
        $model = $this->modelsDep->getById((int)$modelId);
        if (!$model) {
            return self::error('模型不存在');
        }
        if ($model->status !== CommonEnum::YES) {
            return self::error('模型已禁用');
        }

        $driver = $model->driver;
        $modelCode = $model->model_code;
        $endpoint = $model->endpoint ?? '';
        $apiKeyEnc = $model->api_key_enc ?? '';
        $defaultParams = $model->default_params ?? []; // 已被 Model cast 为数组

        // 6. 解密 api_key_enc
        try {
            $apiKey = KeyVault::decrypt($apiKeyEnc);
        } catch (RuntimeException $e) {
            return self::error('API Key 解密失败: ' . $e->getMessage());
        }

        if (empty($apiKey)) {
            return self::error('模型未配置 API Key');
        }

        // 7. 写入用户消息
        $userMessageId = $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_USER,
            'content' => $content,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'total_tokens' => null,
            'cost' => null,
            'model_snapshot' => null,
            'meta_json' => null,
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ]);

        // 8. 组装 messages[]
        $messages = [];

        // 8.1 system prompt
        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        // 8.2 历史消息：取最近 max_history*2 条（按 id desc），再 reverse
        $historyMessages = $this->getRecentMessages($conversationId, $maxHistory * 2);
        foreach ($historyMessages as $msg) {
            $roleStr = AiEnum::$roleArr[$msg['role']] ?? null;
            if ($roleStr) {
                $messages[] = ['role' => $roleStr, 'content' => $msg['content']];
            }
        }

        // 9. 合并参数
        $params = $defaultParams;
        if ($temperature !== null) {
            $params['temperature'] = (float)$temperature;
        }
        if ($maxTokens !== null) {
            $params['max_tokens'] = (int)$maxTokens;
        }
        // 用 agent 的 extra_params 覆盖
        $params = array_merge($params, $extraParams);

        // 构建最终 payload
        $payload = array_merge($params, [
            'model' => $modelCode,
            'messages' => $messages,
        ]);

        // 10. 使用工厂创建 AI 客户端
        if (!AiClientFactory::isSupported($driver)) {
            return self::error('不支持的 AI 驱动: ' . $driver);
        }
        $client = AiClientFactory::create($driver);

        // 11. 调用 AI API
        try {
            $result = $client->chatCompletions($payload, [
                'endpoint' => $endpoint,
                'apiKey' => $apiKey,
            ]);
        } catch (RuntimeException $e) {
            return self::error('AI 调用失败: ' . $e->getMessage());
        }

        // 12. 解析返回（工厂已统一格式）
        $assistantContent = $result['content'];
        $promptTokens = $result['usage']['prompt_tokens'];
        $completionTokens = $result['usage']['completion_tokens'];
        $totalTokens = $result['usage']['total_tokens'];
        $rawResponse = $result['raw'];

        // 13. 写入 assistant 消息
        $assistantMessageId = $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_ASSISTANT,
            'content' => $assistantContent,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'cost' => null,
            'model_snapshot' => $modelCode,
            'meta_json' => json_encode(['request_id' => $rawResponse['id'] ?? null], JSON_UNESCAPED_UNICODE),
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ]);

        // 更新会话的 last_message_at
        $this->conversationsDep->updateLastMessageAt($conversationId);

        // 返回必要数据
        return self::success([
            'conversation_id' => $conversationId,
        ]);
    }

    /**
     * 获取最近的历史消息
     */
    private function getRecentMessages(int $conversationId, int $limit): array
    {
        $messages = $this->messagesDep->getRecentByConversationId($conversationId, $limit);
        // 反转为正序（从旧到新）
        return array_reverse($messages->toArray());
    }

    /**
     * 发送消息并获取 AI 回复（流式 SSE）
     * @param array $param 已校验的参数
     * @param int $userId 用户 ID
     * @param callable $onChunk 回调函数 function(string $event, array $data)
     * @return array ['code' => int, 'msg' => string, 'data' => array]
     */
    public function sendStream(array $param, int $userId, callable $onChunk): array
    {
        $conversationId = $param['conversation_id'] ?? null;
        $agentId = $param['agent_id'] ?? null;
        $content = $param['content'];
        $maxHistory = (int)($param['max_history'] ?? 20);

        // 1. 处理会话
        $isNewConversation = false;
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
            $isNewConversation = true;
            // 通知前端新会话 ID
            $onChunk('conversation', ['conversation_id' => $conversationId]);
        } else {
            $conversation = $this->conversationsDep->getById((int)$conversationId, $userId);
            if (!$conversation) {
                return self::error('会话不存在');
            }
            $agentId = $conversation->agent_id;
        }

        // 2. 查 ai_agents
        $agent = $this->agentsDep->getById((int)$agentId);
        if (!$agent) {
            return self::error('智能体不存在');
        }
        if ($agent->status !== CommonEnum::YES) {
            return self::error('智能体已禁用');
        }

        $systemPrompt = $agent->system_prompt ?? '';
        $temperature = $agent->temperature;
        $maxTokens = $agent->max_tokens;
        $extraParams = $agent->extra_params ?? [];
        $modelId = $agent->model_id;

        // 3. 查 ai_models
        $model = $this->modelsDep->getById((int)$modelId);
        if (!$model) {
            return self::error('模型不存在');
        }
        if ($model->status !== CommonEnum::YES) {
            return self::error('模型已禁用');
        }

        $driver = $model->driver;
        $modelCode = $model->model_code;
        $endpoint = $model->endpoint ?? '';
        $apiKeyEnc = $model->api_key_enc ?? '';
        $defaultParams = $model->default_params ?? [];

        // 4. 解密 api_key_enc
        try {
            $apiKey = KeyVault::decrypt($apiKeyEnc);
        } catch (RuntimeException $e) {
            return self::error('API Key 解密失败: ' . $e->getMessage());
        }

        if (empty($apiKey)) {
            return self::error('模型未配置 API Key');
        }

        // 5. 写入用户消息
        $userMessageId = $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_USER,
            'content' => $content,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'total_tokens' => null,
            'cost' => null,
            'model_snapshot' => null,
            'meta_json' => null,
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ]);

        // 6. 组装 messages[]
        $messages = [];
        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $historyMessages = $this->getRecentMessages($conversationId, $maxHistory * 2);
        foreach ($historyMessages as $msg) {
            $roleStr = AiEnum::$roleArr[$msg['role']] ?? null;
            if ($roleStr) {
                $messages[] = ['role' => $roleStr, 'content' => $msg['content']];
            }
        }

        // 7. 合并参数
        $params = $defaultParams;
        if ($temperature !== null) {
            $params['temperature'] = (float)$temperature;
        }
        if ($maxTokens !== null) {
            $params['max_tokens'] = (int)$maxTokens;
        }
        $params = array_merge($params, $extraParams);

        $payload = array_merge($params, [
            'model' => $modelCode,
            'messages' => $messages,
        ]);

        // 8. 使用工厂创建 AI 客户端
        if (!AiClientFactory::isSupported($driver)) {
            return self::error('不支持的 AI 驱动: ' . $driver);
        }
        $client = AiClientFactory::create($driver);

        // 9. 调用 AI API（流式）
        try {
            $result = $client->chatCompletionsStream($payload, [
                'endpoint' => $endpoint,
                'apiKey' => $apiKey,
            ], function ($deltaContent, $chunk) use ($onChunk) {
                // 将每个 chunk 转发给前端
                $onChunk('content', ['delta' => $deltaContent]);
            });
        } catch (RuntimeException $e) {
            return self::error('AI 调用失败: ' . $e->getMessage());
        }

        // 10. 写入 assistant 消息
        $assistantContent = $result['content'];
        $promptTokens = $result['usage']['prompt_tokens'];
        $completionTokens = $result['usage']['completion_tokens'];
        $totalTokens = $result['usage']['total_tokens'];
        $requestId = $result['request_id'] ?? null;

        $assistantMessageId = $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_ASSISTANT,
            'content' => $assistantContent,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'cost' => null,
            'model_snapshot' => $modelCode,
            'meta_json' => $requestId ? json_encode(['request_id' => $requestId], JSON_UNESCAPED_UNICODE) : null,
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ]);

        // 更新会话的 last_message_at
        $this->conversationsDep->updateLastMessageAt($conversationId);

        // 新会话时自动生成标题
        if ($isNewConversation) {
            $this->generateTitle($conversationId, $content, $userId, $client, $endpoint, $apiKey, $modelCode);
        }

        // 通知前端流结束
        $onChunk('done', ['conversation_id' => $conversationId]);

        return self::success(['conversation_id' => $conversationId]);
    }

    /**
     * 自动生成会话标题
     */
    private function generateTitle(int $conversationId, string $userMessage, int $userId, $client, string $endpoint, string $apiKey, string $modelCode): void
    {
        try {
            $prompt = "请根据以下用户消息，生成一个简短的会话标题（不超过20个字），直接返回标题文本，不要任何解释：\n\n" . $userMessage;
            
            $result = $client->chatCompletions([
                'model' => $modelCode,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 50,
                'temperature' => 0.7,
            ], [
                'endpoint' => $endpoint,
                'apiKey' => $apiKey,
            ]);

            $title = trim($result['content'] ?? '');
            // 清理标题（去除引号、截断长度）
            $title = trim($title, '"\'「」『』');
            if (mb_strlen($title) > 30) {
                $title = mb_substr($title, 0, 30) . '...';
            }

            if (!empty($title)) {
                $this->conversationsDep->updateTitle($conversationId, $title, $userId);
            }
        } catch (\Throwable $e) {
            // 生成标题失败不影响主流程，只记录日志
            \support\Log::warning('AI 生成标题失败', ['error' => $e->getMessage()]);
        }
    }
}

<?php

namespace app\service\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiModelsDep;
use app\enum\AiEnum;
use app\lib\Ai\Crypto\KeyVault;
use app\lib\Ai\AiClientFactory;
use app\lib\Ai\AiClientInterface;
use RuntimeException;

/**
 * AI 对话服务
 * 负责纯 AI 调用逻辑：构建消息、构建 payload、调用 API
 */
class AiChatService
{
    protected AiMessagesDep $messagesDep;
    protected AiAgentsDep $agentsDep;
    protected AiModelsDep $modelsDep;

    public function __construct()
    {
        $this->messagesDep = new AiMessagesDep();
        $this->agentsDep = new AiAgentsDep();
        $this->modelsDep = new AiModelsDep();
    }

    /**
     * 创建 AI 客户端
     * @param object $model 模型对象
     * @return array [client, config, error]
     */
    public function createClient(object $model): array
    {
        // 解密 API Key
        try {
            $apiKey = KeyVault::decrypt($model->api_key_enc ?? '');
        } catch (RuntimeException $e) {
            return [null, null, 'API Key 解密失败: ' . $e->getMessage()];
        }
        if (empty($apiKey)) {
            return [null, null, '模型未配置 API Key'];
        }

        // 创建客户端
        if (!AiClientFactory::isSupported($model->driver)) {
            return [null, null, '不支持的 AI 驱动: ' . $model->driver];
        }

        $client = AiClientFactory::create($model->driver);
        
        // 如果模型未配置 endpoint，使用驱动默认的 baseUrl
        $endpoint = $model->endpoint ?? '';
        if (empty($endpoint)) {
            $endpoint = AiClientFactory::getDefaultBaseUrl($model->driver);
        }
        
        $config = [
            'endpoint' => $endpoint,
            'apiKey' => $apiKey,
        ];

        return [$client, $config, null];
    }

    /**
     * 组装发给 AI 的消息列表
     * @param object $agent 智能体对象
     * @param int $conversationId 会话 ID
     * @param int $maxHistory 最大历史消息数
     * @param array|null $modalities 模型多模态能力 {"image": true, ...}
     * @return array messages 数组
     */
    public function buildMessages(object $agent, int $conversationId, int $maxHistory, ?array $modalities = null): array
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
            if (!$roleStr) {
                continue;
            }

            // 从 meta_json 提取附件
            $attachments = [];
            if (!empty($msg['meta_json'])) {
                $metaJson = is_string($msg['meta_json']) ? json_decode($msg['meta_json'], true) : $msg['meta_json'];
                $attachments = $metaJson['attachments'] ?? [];
            }

            // 构建消息内容（支持多模态）
            $content = $this->buildMultimodalContent($msg['content'] ?? '', $attachments, $modalities);

            $messages[] = ['role' => $roleStr, 'content' => $content];
        }

        return $messages;
    }

    /**
     * 构建 AI 请求 payload
     * @param object $agent 智能体对象
     * @param object $model 模型对象
     * @param array $messages 消息列表
     * @return array payload
     */
    public function buildPayload(object $agent, object $model, array $messages): array
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
     * 构建多模态消息内容
     * @param string $text 文本内容
     * @param array $attachments 附件列表
     * @param array|null $modalities 模型多模态能力
     * @return string|array 纯文本或多模态数组
     */
    public function buildMultimodalContent(string $text, array $attachments, ?array $modalities): string|array
    {
        $supportsImage = $modalities['image'] ?? false;

        // 不支持图片或无图片：返回纯文本
        if (!$supportsImage || empty($attachments)) {
            return $text;
        }

        // 过滤出图片类型的附件
        $imageAttachments = array_filter($attachments, fn($a) => ($a['type'] ?? '') === 'image');
        if (empty($imageAttachments)) {
            return $text;
        }

        // 支持图片：构建多模态格式
        $content = [];

        // 添加文本
        if (!empty($text)) {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        // 添加图片
        foreach ($imageAttachments as $attachment) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $attachment['url']]
            ];
        }

        return $content;
    }

    /**
     * 调用 AI（非流式）
     * @param AiClientInterface $client AI 客户端
     * @param array $payload 请求 payload
     * @param array $config 配置（endpoint, apiKey）
     * @return array AI 响应
     */
    public function chat(AiClientInterface $client, array $payload, array $config): array
    {
        return $client->chatCompletions($payload, $config);
    }

    /**
     * 调用 AI（流式）
     * @param AiClientInterface $client AI 客户端
     * @param array $payload 请求 payload
     * @param array $config 配置
     * @param callable $onDelta 回调函数 function(string $delta)
     * @return array AI 响应（包含完整 content 和 usage）
     */
    public function chatStream(AiClientInterface $client, array $payload, array $config, callable $onDelta): array
    {
        return $client->chatCompletionsStream($payload, $config, function ($delta, $chunk) use ($onDelta) {
            // $chunk 保留用于未来扩展（如 tool_calls、usage 实时统计等）
            $onDelta($delta);
        });
    }

    /**
     * 自动生成会话标题
     * @param AiClientInterface $client AI 客户端
     * @param array $config 配置
     * @param string $modelCode 模型代码
     * @param string $userMessage 用户消息
     * @return string|null 生成的标题，失败返回 null
     */
    public function generateTitle(
        AiClientInterface $client,
        array $config,
        string $modelCode,
        string $userMessage
    ): ?string {
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

            return !empty($title) ? $title : null;
        } catch (\Throwable $e) {
            // 标题生成失败不影响主流程，静默处理
            return null;
        }
    }

    /**
     * 生成唯一的 request_id
     */
    public function generateRequestId(): string
    {
        return sprintf('%s-%s', date('YmdHis'), bin2hex(random_bytes(8)));
    }
}

<?php

namespace app\service\Ai;

use app\dep\Ai\AiMessagesDep;
use app\enum\AiEnum;
use app\lib\Ai\AiClientFactory;
use app\lib\Ai\AiClientInterface;
use app\lib\Crypto\KeyVault;
use RuntimeException;

/**
 * AI 对话服务
 * 负责纯 AI 调用逻辑：创建客户端、构建消息、构建 payload、调用 API
 * 不涉及业务编排（会话管理、Run/Step 记录由 AiChatModule 负责）
 */
class AiChatService
{
    private static ?AiMessagesDep $messagesDep = null;

    private static function msgDep(): AiMessagesDep
    {
        return self::$messagesDep ??= new AiMessagesDep();
    }

    /**
     * 创建 AI 客户端（解密 API Key + 实例化驱动）
     *
     * @param object $model 模型对象
     * @return array{0: ?AiClientInterface, 1: ?array, 2: ?string} [client, config, error]
     */
    public static function createClient(object $model): array
    {
        // 解密 API Key
        try {
            $apiKey = KeyVault::decrypt($model->api_key_enc ?? '');
        } catch (RuntimeException $e) {
            return [null, null, "API Key 解密失败: {$e->getMessage()}"];
        }
        if (empty($apiKey)) {
            return [null, null, '模型未配置 API Key'];
        }

        // 校验驱动支持
        if (!AiClientFactory::isSupported($model->driver)) {
            return [null, null, "不支持的 AI 驱动: {$model->driver}"];
        }

        $client = AiClientFactory::create($model->driver);

        // 未配置 endpoint 时使用驱动默认 baseUrl
        $endpoint = $model->endpoint ?? '';
        if (empty($endpoint)) {
            $endpoint = AiClientFactory::getDefaultBaseUrl($model->driver);
        }

        return [$client, ['endpoint' => $endpoint, 'apiKey' => $apiKey], null];
    }


    /**
     * 组装发给 AI 的消息列表（system prompt + 历史消息）
     *
     * @param object     $agent          智能体对象（含 system_prompt）
     * @param int        $conversationId 会话 ID
     * @param int        $maxHistory     最大历史消息轮数
     * @param array|null $modalities     模型多模态能力 {"image": true, ...}
     * @return array messages 数组
     */
    public static function buildMessages(object $agent, int $conversationId, int $maxHistory, ?array $modalities = null): array
    {
        $messages = [];

        // system prompt
        if (!empty($agent->system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $agent->system_prompt];
        }

        // 历史消息（取最近 maxHistory*2 条，倒序查询后反转为正序）
        $history = self::msgDep()->getRecentByConversationId($conversationId, $maxHistory * 2);
        foreach (\array_reverse($history->toArray()) as $msg) {
            $roleStr = AiEnum::$roleArr[$msg['role']] ?? null;
            if (!$roleStr) {
                continue;
            }

            // assistant 消息始终是纯文本
            if ($msg['role'] === AiEnum::ROLE_ASSISTANT) {
                $messages[] = ['role' => $roleStr, 'content' => $msg['content'] ?? ''];
                continue;
            }

            // user 消息：从 meta_json 提取附件，构建多模态内容
            $attachments = [];
            if (!empty($msg['meta_json'])) {
                $metaJson = \is_string($msg['meta_json']) ? json_decode($msg['meta_json'], true) : $msg['meta_json'];
                $attachments = $metaJson['attachments'] ?? [];
            }

            $content = self::buildMultimodalContent($msg['content'] ?? '', $attachments, $modalities);
            $messages[] = ['role' => $roleStr, 'content' => $content];
        }

        return $messages;
    }

    /**
     * 构建 AI 请求 payload（合并模型默认参数 + 智能体覆盖参数）
     */
    public static function buildPayload(object $agent, object $model, array $messages): array
    {
        $params = $model->default_params ?? [];

        // 智能体参数覆盖
        if ($agent->temperature !== null) {
            $params['temperature'] = (float)$agent->temperature;
        }
        if ($agent->max_tokens !== null) {
            $params['max_tokens'] = (int)$agent->max_tokens;
        }
        $params = array_merge($params, $agent->extra_params ?? []);

        return array_merge($params, [
            'model'    => $model->model_code,
            'messages' => $messages,
        ]);
    }

    /**
     * 构建多模态消息内容
     * 支持图片时返回 [{type: text}, {type: image_url}] 数组，否则返回纯文本
     */
    public static function buildMultimodalContent(string $text, array $attachments, ?array $modalities): string|array
    {
        $supportsImage = $modalities['image'] ?? false;

        if (!$supportsImage || empty($attachments)) {
            return $text;
        }

        // 过滤出图片附件，array_values 重置索引避免 json_encode 输出对象
        $imageAttachments = \array_values(\array_filter($attachments, fn($a) => ($a['type'] ?? '') === 'image'));
        if (empty($imageAttachments)) {
            return $text;
        }

        $content = [];
        if (!empty($text)) {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        foreach ($imageAttachments as $attachment) {
            $url = $attachment['url'] ?? '';
            if (!empty($url)) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
            }
        }

        // 过滤后没有有效图片，退回纯文本
        if (\count($content) <= 1 && !empty($text)) {
            return $text;
        }

        return $content;
    }

    /**
     * 调用 AI（非流式）
     */
    public static function chat(AiClientInterface $client, array $payload, array $config): array
    {
        return $client->chatCompletions($payload, $config);
    }

    /**
     * 调用 AI（流式，通过 onDelta 回调逐 token 推送）
     */
    public static function chatStream(AiClientInterface $client, array $payload, array $config, callable $onDelta, ?callable $shouldStop = null): array
    {
        return $client->chatCompletionsStream($payload, $config, function ($delta, $chunk) use ($onDelta) {
            $onDelta($delta);
        }, $shouldStop);
    }

    /**
     * 自动生成会话标题（非流式调用，失败静默返回 null）
     */
    public static function generateTitle(AiClientInterface $client, array $config, string $modelCode, string $userMessage): ?string
    {
        try {
            $prompt = "请根据以下用户消息，生成一个简短的会话标题（不超过20个字），直接返回标题文本，不要任何解释：\n\n{$userMessage}";

            $result = $client->chatCompletions([
                'model'       => $modelCode,
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'max_tokens'  => 50,
                'temperature' => 0.7,
            ], $config);

            $title = trim($result['content'] ?? '');
            $title = trim($title, "\"'「」『』");

            if (mb_strlen($title) > 30) {
                $title = mb_substr($title, 0, 30) . '...';
            }

            return !empty($title) ? $title : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 生成唯一的 request_id（时间戳 + 随机 hex）
     */
    public static function generateRequestId(): string
    {
        return date('YmdHis') . '-' . bin2hex(random_bytes(8));
    }
}
<?php

namespace app\service\Ai;

use app\dep\Ai\AiMessagesDep;
use app\enum\AiEnum;
use app\lib\Ai\NeuronAgentFactory;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;


/**
 * AI 对话服务
 * 基于 Neuron AI 框架，负责 Provider 创建、消息构建、AI 调用
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
     * 创建 Neuron AI Provider（解密 API Key + 实例化驱动）
     */
    public static function createProvider(object $model): array
    {
        return NeuronAgentFactory::createProvider($model);
    }

    /**
     * 创建完整的 Neuron Agent（含 Provider + 系统提示词）
     */
    public static function createAgent(object $model, object $agent, ?array $runtimeParams = null): array
    {
        return NeuronAgentFactory::createAgent($model, $agent, $runtimeParams);
    }

    /**
     * 组装发给 AI 的消息列表（system prompt + 历史消息）
     * @param int|null $excludeMessageId 排除指定消息ID（避免刚插入的用户消息被重复发送）
     */
    public static function buildMessages(object $agent, int $conversationId, int $maxHistory, ?array $modalities = null, ?int $excludeMessageId = null): array
    {
        $messages = [];

        if (!empty($agent->system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $agent->system_prompt];
        }

        // 历史消息（取最近 maxHistory*2 条，倒序查询后反转为正序）
        $history = self::msgDep()->getRecentByConversationId($conversationId, $maxHistory * 2);
        foreach (\array_reverse($history->toArray()) as $msg) {
            // 排除刚插入的用户消息，避免与 userContent 重复
            if ($excludeMessageId && ($msg['id'] ?? 0) === $excludeMessageId) {
                continue;
            }

            $roleStr = AiEnum::$roleArr[$msg['role']] ?? null;
            if (!$roleStr) {
                continue;
            }

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
     * 构建多模态消息内容
     */
    public static function buildMultimodalContent(string $text, array $attachments, ?array $modalities): string|array
    {
        $supportsImage = $modalities['image'] ?? false;

        if (!$supportsImage || empty($attachments)) {
            return $text;
        }

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

        if (\count($content) <= 1 && !empty($text)) {
            return $text;
        }

        return $content;
    }

    /**
     * 调用 AI（非流式）— 使用 Neuron Agent
     *
     * Agent::chat() 返回 AgentHandler，调用 ->getMessage() 获取最终 Message
     */
    public static function chat(Agent $agent, string|array $content, array $history = []): array
    {
        // 加载历史消息到 Agent 的 ChatHistory
        self::loadHistory($agent, $history);

        $userMessage = self::createUserMessage($content);
        $handler  = $agent->chat($userMessage);
        $message  = $handler->getMessage();

        return [
            'content'    => $message->getContent() ?? '',
            'usage'      => self::extractUsage($message),
            'request_id' => null,
        ];
    }

    /**
     * 调用 AI（流式）— 使用 Neuron Agent
     *
     * Agent::stream() 返回 AgentHandler，调用 ->events() 获取 Generator<StreamChunk>
     * TextChunk->content 是每个增量文本片段
     */
    public static function chatStream(Agent $agent, string|array $content, array $history, callable $onDelta, ?callable $shouldStop = null): array
    {
        self::loadHistory($agent, $history);

        $fullContent = '';
        $canceled    = false;

        $userMessage = self::createUserMessage($content);
        $handler = $agent->stream($userMessage);

        foreach ($handler->events() as $chunk) {
            if ($shouldStop && $shouldStop()) {
                $canceled = true;
                break;
            }

            // 只处理文本 chunk
            if ($chunk instanceof TextChunk) {
                $fullContent .= $chunk->content;
                $onDelta($chunk->content);
            }
        }

        if ($canceled) {
            return [
                'content'    => $fullContent,
                'usage'      => ['prompt_tokens' => null, 'completion_tokens' => null, 'total_tokens' => null],
                'request_id' => null,
                'canceled'   => true,
            ];
        }

        return [
            'content'    => $fullContent,
            'usage'      => ['prompt_tokens' => null, 'completion_tokens' => null, 'total_tokens' => null],
            'request_id' => null,
        ];
    }

    /**
     * 自动生成会话标题（非流式调用，失败静默返回 null）
     */
    public static function generateTitle(Agent $agent, string $userMessage): ?string
    {
        try {
            $prompt = "请根据以下用户消息，生成一个简短的会话标题（不超过20个字），直接返回标题文本，不要任何解释：\n\n{$userMessage}";

            $handler  = $agent->chat(new UserMessage($prompt));
            $message  = $handler->getMessage();

            $title = trim($message->getContent() ?? '');
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
     * 生成唯一的 request_id
     */
    public static function generateRequestId(): string
    {
        return date('YmdHis') . '-' . bin2hex(random_bytes(8));
    }

    // ==================== 私有方法 ====================

    /**
     * 将历史消息加载到 Neuron Agent 的 ChatHistory
     */
    private static function loadHistory(Agent $agent, array $messages): void
    {
        $chatHistory = $agent->getChatHistory();

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            if ($role === 'system') {
                continue; // 已通过 setInstructions 设置
            }

            $content = $msg['content'] ?? '';

            if ($role === 'user') {
                $chatHistory->addMessage(self::createUserMessage($content));
            } elseif ($role === 'assistant') {
                $textContent = \is_array($content)
                    ? implode("\n", \array_column(\array_filter($content, fn($p) => ($p['type'] ?? '') === 'text'), 'text'))
                    : $content;
                $chatHistory->addMessage(new AssistantMessage($textContent));
            }
        }
    }

    /**
     * 根据内容类型创建 UserMessage（支持纯文本和多模态）
     * $content 为 string 时创建纯文本消息，为 array 时创建多模态消息
     */
    private static function createUserMessage(string|array $content): UserMessage
    {
        if (\is_string($content)) {
            return new UserMessage($content);
        }

        // 多模态内容：[{type: text, text: ...}, {type: image_url, image_url: {url: ...}}]
        $blocks = [];
        foreach ($content as $part) {
            $type = $part['type'] ?? '';
            if ($type === 'text') {
                $blocks[] = new TextContent($part['text'] ?? '');
            } elseif ($type === 'image_url') {
                $url = $part['image_url']['url'] ?? '';
                if (!empty($url)) {
                    $blocks[] = new ImageContent($url, SourceType::URL);
                }
            }
        }

        return !empty($blocks) ? new UserMessage($blocks) : new UserMessage('');
    }

    /**
     * 从 Message 提取 usage 信息
     */
    private static function extractUsage($message): array
    {
        // Neuron v3 Message 有 getUsage() 方法，返回 Usage 对象或 null
        $usage = method_exists($message, 'getUsage') ? $message->getUsage() : null;

        if ($usage) {
            return [
                'prompt_tokens'     => $usage->input_tokens ?? null,
                'completion_tokens' => $usage->output_tokens ?? null,
                'total_tokens'      => ($usage->input_tokens ?? 0) + ($usage->output_tokens ?? 0) ?: null,
            ];
        }

        return [
            'prompt_tokens'     => null,
            'completion_tokens' => null,
            'total_tokens'      => null,
        ];
    }
}

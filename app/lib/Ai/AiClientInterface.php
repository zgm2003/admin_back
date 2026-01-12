<?php

namespace app\lib\Ai;

/**
 * AI 客户端接口
 * 所有 AI 驱动客户端必须实现此接口
 */
interface AiClientInterface
{
    /**
     * 调用 chat/completions 接口（非流式）
     * @param array $payload 请求参数（model, messages, temperature, max_tokens 等）
     * @param array $config 配置信息（baseUrl, apiKey, endpoint 等）
     * @return array 返回结构：['content' => string, 'usage' => array, 'raw' => array]
     */
    public function chatCompletions(array $payload, array $config): array;

    /**
     * 调用 chat/completions 接口（流式 SSE）
     * @param array $payload 请求参数（model, messages, temperature, max_tokens 等）
     * @param array $config 配置信息（baseUrl, apiKey, endpoint 等）
     * @param callable $onChunk 每收到一块数据时的回调 function(string $content, array $chunk)
     * @param callable|null $shouldStop 检查是否应该停止的回调 function(): bool
     * @return array 返回结构：['content' => string, 'usage' => array]
     */
    public function chatCompletionsStream(array $payload, array $config, callable $onChunk, ?callable $shouldStop = null): array;

    /**
     * 获取默认的 baseUrl
     * @return string
     */
    public function getDefaultBaseUrl(): string;
}

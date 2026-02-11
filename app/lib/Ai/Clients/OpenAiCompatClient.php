<?php

namespace app\lib\Ai\Clients;

use app\lib\Ai\AiClientInterface;
use RuntimeException;

/**
 * OpenAI 兼容接口客户端
 * 支持通义千问、DeepSeek、Moonshot、智谱等 OpenAI 兼容接口
 */
class OpenAiCompatClient implements AiClientInterface
{
    private string $defaultBaseUrl;

    public function __construct(string $defaultBaseUrl = '')
    {
        $this->defaultBaseUrl = $defaultBaseUrl;
    }

    /**
     * 获取默认的 baseUrl
     */
    public function getDefaultBaseUrl(): string
    {
        return $this->defaultBaseUrl;
    }

    /**
     * 调用 chat/completions 接口
     * @param array $payload 请求参数（model, messages, temperature, max_tokens 等）
     * @param array $config 配置信息（baseUrl, apiKey, endpoint 等）
     * @return array 返回结构：['content' => string, 'usage' => array, 'raw' => array]
     * @throws RuntimeException
     */
    public function chatCompletions(array $payload, array $config): array
    {
        $baseUrl = $config['endpoint'] ?? $config['baseUrl'] ?? $this->defaultBaseUrl;
        $apiKey = $config['apiKey'] ?? '';

        if (empty($baseUrl)) {
            throw new RuntimeException('未配置 API baseUrl');
        }
        if (empty($apiKey)) {
            throw new RuntimeException('未配置 API Key');
        }

        // 移除 baseUrl 末尾可能已包含的 /chat/completions
        $baseUrl = rtrim($baseUrl, '/');
        $baseUrl = preg_replace('#/chat/completions$#', '', $baseUrl);
        $url = $baseUrl . '/chat/completions';

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('请求失败: ' . $curlError);
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('响应解析失败: ' . json_last_error_msg());
        }

        // 处理 API 错误响应
        if ($httpCode >= 400 || isset($result['error'])) {
            $errorMsg = $result['error']['message'] ?? ($result['message'] ?? '未知错误');
            throw new RuntimeException('API 错误 [' . $httpCode . ']: ' . $errorMsg);
        }

        // 统一返回格式
        return [
            'content' => $result['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
            ],
            'raw' => $result,
        ];
    }

    /**
     * 调用 chat/completions 接口（流式 SSE）
     * @param array $payload 请求参数
     * @param array $config 配置信息
     * @param callable $onChunk 回调函数 function(string $deltaContent, array $chunk)
     * @param callable|null $shouldStop 检查是否应该停止的回调 function(): bool
     * @return array 返回结构：['content' => string, 'usage' => array]
     */
    public function chatCompletionsStream(array $payload, array $config, callable $onChunk, ?callable $shouldStop = null): array
    {
        $baseUrl = $config['endpoint'] ?? $config['baseUrl'] ?? $this->defaultBaseUrl;
        $apiKey = $config['apiKey'] ?? '';

        if (empty($baseUrl)) {
            throw new RuntimeException('未配置 API baseUrl');
        }
        if (empty($apiKey)) {
            throw new RuntimeException('未配置 API Key');
        }

        // 移除 baseUrl 末尾可能已包含的 /chat/completions
        $baseUrl = rtrim($baseUrl, '/');
        $baseUrl = preg_replace('#/chat/completions$#', '', $baseUrl);
        $url = $baseUrl . '/chat/completions';

        // 强制开启 stream 并请求返回 usage
        $payload['stream'] = true;
        $payload['stream_options'] = ['include_usage' => true];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $fullContent = '';
        $usage = ['prompt_tokens' => null, 'completion_tokens' => null, 'total_tokens' => null];
        $requestId = null;
        $canceled = false;
        $sseBuffer = ''; // 缓冲不完整的 SSE 行

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiKey}",
                'Accept: text/event-stream',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullContent, &$usage, &$requestId, &$canceled, &$sseBuffer, $onChunk, $shouldStop) {
                // 检查是否应该停止
                if ($shouldStop && $shouldStop()) {
                    $canceled = true;
                    return 0; // 返回 0 中断 curl
                }

                // 将新数据追加到缓冲区，按完整行处理
                $sseBuffer .= $data;
                $lines = explode("\n", $sseBuffer);
                // 最后一个元素可能是不完整的行，保留到下次
                $sseBuffer = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, ':')) {
                        continue;
                    }
                    if (!str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $jsonStr = trim(substr($line, 5));
                    if ($jsonStr === '[DONE]') {
                        continue;
                    }
                    $chunk = json_decode($jsonStr, true);
                    if (!$chunk) {
                        continue;
                    }
                    // 捕获 request_id
                    if (isset($chunk['id']) && !$requestId) {
                        $requestId = $chunk['id'];
                    }
                    // 捕获内容
                    if (isset($chunk['choices'][0]['delta']['content'])) {
                        $deltaContent = $chunk['choices'][0]['delta']['content'];
                        $fullContent .= $deltaContent;
                        $onChunk($deltaContent, $chunk);
                    }
                    // 捕获 usage（通常在最后一个 chunk）
                    if (isset($chunk['usage'])) {
                        $usage = [
                            'prompt_tokens' => $chunk['usage']['prompt_tokens'] ?? null,
                            'completion_tokens' => $chunk['usage']['completion_tokens'] ?? null,
                            'total_tokens' => $chunk['usage']['total_tokens'] ?? null,
                        ];
                    }
                }
                return \strlen($data);
            },
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // 如果是被取消的，不抛异常
        if ($canceled) {
            return [
                'content' => $fullContent,
                'usage' => $usage,
                'request_id' => $requestId,
                'canceled' => true,
            ];
        }

        if ($result === false && !$canceled) {
            throw new RuntimeException('请求失败: ' . $curlError);
        }

        if ($httpCode >= 400) {
            throw new RuntimeException('API 错误 [' . $httpCode . ']');
        }

        return [
            'content' => $fullContent,
            'usage' => $usage,
            'request_id' => $requestId,
        ];
    }
}

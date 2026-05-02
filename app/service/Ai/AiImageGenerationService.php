<?php

namespace app\service\Ai;

use app\enum\UploadConfigEnum;
use app\lib\Crypto\KeyVault;
use app\service\System\UploadService;
use GuzzleHttp\Client;
use RuntimeException;

class AiImageGenerationService
{
    public function generate(object $model, string $prompt, array $options = []): array
    {
        $payload = $this->requestImage($model, $prompt, $options);
        $upload = $this->persist($payload);

        return [
            'type' => 'image',
            'url' => (string)($upload['url'] ?? ''),
            'alt' => mb_substr($prompt, 0, 120),
            'meta' => [
                'model_code' => (string)($model->model_code ?? ''),
                'prompt' => $prompt,
                'upload' => $upload,
                'revised_prompt' => $this->extractRevisedPrompt($payload),
            ],
        ];
    }

    private function requestImage(object $model, string $prompt, array $options): array
    {
        $apiKey = KeyVault::decrypt((string)($model->api_key_enc ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('图片模型未配置 API Key');
        }

        $baseUri = rtrim((string)($model->endpoint ?: 'https://api.openai.com/v1'), '/');
        $client = new Client([
            'timeout' => (int)($options['timeout'] ?? 180),
            'connect_timeout' => (int)($options['connect_timeout'] ?? 20),
            'verify' => !str_starts_with($baseUri, 'http://localhost') && !str_starts_with($baseUri, 'http://127.0.0.1'),
        ]);

        $json = [
            'model' => (string)$model->model_code,
            'prompt' => $prompt,
            'size' => (string)($options['size'] ?? '1024x1024'),
            'n' => 1,
            'response_format' => 'b64_json',
        ];
        $quality = (string)($options['quality'] ?? 'auto');
        if ($quality !== '') {
            $json['quality'] = $quality;
        }

        $response = $client->post($baseUri . '/images/generations', [
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $json,
        ]);

        $body = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('图片模型请求失败: HTTP ' . $statusCode . ' ' . mb_substr($body, 0, 500));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('图片模型返回不可解析');
        }

        return $decoded;
    }

    private function persist(array $payload): array
    {
        $item = $this->extractImageItem($payload);
        $filename = 'ai_chat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
        $upload = new UploadService();

        $base64 = (string)($item['b64_json'] ?? $item['result'] ?? '');
        if ($base64 !== '') {
            if (str_contains($base64, ',')) {
                $base64 = substr($base64, strpos($base64, ',') + 1);
            }
            $content = base64_decode($base64, true);
            if ($content === false || $content === '') {
                throw new RuntimeException('图片 base64 解码失败');
            }
            return $upload->uploadContent($content, UploadConfigEnum::FOLDER_AI_CHAT_IMAGES, $filename, date('Ymd'));
        }

        $url = (string)($item['url'] ?? $item['image_url'] ?? '');
        if ($url !== '') {
            return $upload->uploadFromUrl($url, UploadConfigEnum::FOLDER_AI_CHAT_IMAGES, $filename, date('Ymd'));
        }

        throw new RuntimeException('图片模型未返回 b64_json、result 或 url');
    }

    private function extractImageItem(array $payload): array
    {
        $item = $payload['data'][0] ?? null;
        if (is_array($item)) {
            return $item;
        }

        foreach (($payload['output'] ?? []) as $output) {
            if (!is_array($output)) {
                continue;
            }
            if (($output['type'] ?? '') === 'image_generation_call' && !empty($output['result'])) {
                return ['result' => $output['result']];
            }
            foreach (($output['content'] ?? []) as $content) {
                if (!is_array($content)) {
                    continue;
                }
                if (!empty($content['image_url'])) {
                    return ['image_url' => $content['image_url']];
                }
                if (!empty($content['b64_json'])) {
                    return ['b64_json' => $content['b64_json']];
                }
            }
        }

        throw new RuntimeException('图片模型未返回可识别的图片内容');
    }

    private function extractRevisedPrompt(array $payload): ?string
    {
        $item = $payload['data'][0] ?? null;
        return is_array($item) && isset($item['revised_prompt']) ? (string)$item['revised_prompt'] : null;
    }
}

<?php

namespace app\service\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiModelsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\enum\UploadConfigEnum;
use app\lib\Crypto\KeyVault;
use app\service\System\UploadService;
use GuzzleHttp\Client;
use RuntimeException;

/**
 * AI短剧分镜图片生成服务。
 *
 * 输入来自 cine_assets.prompt 或 AI 工具参数；
 * 输出必须是可展示的 file_url，不生成最终视频。
 */
class CineImageGenerationService
{
    public function generateKeyframe(array $input, bool $dryRun = false): array
    {
        $payload = $this->normalizeInput($input);
        if ($dryRun) {
            return [
                'status' => 'prepared',
                'message' => '分镜图片生成请求包已准备；dry_run=true 未调用图片模型。',
                'model_scene' => AiEnum::SCENE_CINE_KEYFRAME,
                'model_code' => 'gpt-image-2',
                'asset_type' => 'keyframe',
                'shot_id' => $payload['shot_id'],
                'request' => $payload,
            ];
        }

        [$model, $agent] = $this->resolveModelAndAgent();
        $apiResponse = $this->requestImage($model, $payload);
        $upload = $this->persistImageResponse($apiResponse, $payload);

        return [
            'status' => 'ready',
            'message' => '分镜图片已生成',
            'agent_id' => (int)$agent->id,
            'model_id' => (int)$model->id,
            'model_code' => (string)$model->model_code,
            'shot_id' => $payload['shot_id'],
            'file_url' => $upload['url'] ?? '',
            'upload' => $upload,
            'revised_prompt' => $this->extractRevisedPrompt($apiResponse),
        ];
    }

    private function normalizeInput(array $input): array
    {
        $prompt = trim((string)($input['image_prompt'] ?? $input['prompt'] ?? ''));
        if ($prompt === '') {
            throw new RuntimeException('image_prompt 不能为空');
        }

        $referenceImages = $input['reference_images'] ?? [];
        if (!\is_array($referenceImages)) {
            $referenceImages = [];
        }

        $shotId = trim((string)($input['shot_id'] ?? 'S01'));
        $aspectRatio = trim((string)($input['aspect_ratio'] ?? '9:16'));
        $style = trim((string)($input['style'] ?? '电影感，连续性优先'));
        $continuityAnchor = trim((string)($input['continuity_anchor'] ?? ''));

        return [
            'project_id' => (int)($input['project_id'] ?? 0),
            'asset_id' => (int)($input['asset_id'] ?? 0),
            'shot_id' => $shotId !== '' ? $shotId : 'S01',
            'prompt' => $this->buildModelPrompt($prompt, $style, $continuityAnchor, $referenceImages),
            'aspect_ratio' => $aspectRatio,
            'size' => $this->sizeFromAspectRatio($aspectRatio),
            'style' => $style,
            'continuity_anchor' => $continuityAnchor,
            'reference_images' => array_values($referenceImages),
        ];
    }

    private function buildModelPrompt(string $prompt, string $style, string $continuityAnchor, array $referenceImages): string
    {
        $parts = [
            '生成一张 AI 短剧静态分镜图片，不是视频帧序列。',
            "画面提示词：{$prompt}",
            "视觉风格：{$style}",
            '硬性限制：no text overlay, no watermark, no subtitles, no logo, no face drift, no random extra characters.',
        ];

        if ($continuityAnchor !== '') {
            $parts[] = "连续性锚点：{$continuityAnchor}";
        }

        if (!empty($referenceImages)) {
            $parts[] = '参考图 URL（用于保持人物/服装/场景连续性）：' . implode('、', array_map('strval', $referenceImages));
        }

        return implode("\n", $parts);
    }

    private function sizeFromAspectRatio(string $aspectRatio): string
    {
        return match ($aspectRatio) {
            '16:9' => '1536x1024',
            '1:1' => '1024x1024',
            default => '1024x1536',
        };
    }

    private function resolveModelAndAgent(): array
    {
        $agent = (new AiAgentsDep())->getBySceneAndMode(AiEnum::SCENE_CINE_KEYFRAME, AiEnum::MODE_TOOL);
        if (!$agent || (int)$agent->status !== CommonEnum::YES) {
            throw new RuntimeException('未配置或未启用短剧分镜图片生成智能体');
        }
        if (($agent->mode ?? '') !== AiEnum::MODE_TOOL) {
            throw new RuntimeException('短剧分镜图片生成智能体必须设置为工具模式');
        }

        $model = (new AiModelsDep())->getOrFail((int)$agent->model_id);
        if ((int)$model->status !== CommonEnum::YES || (int)$model->is_del !== CommonEnum::NO) {
            throw new RuntimeException('短剧分镜图片模型未启用');
        }

        return [$model, $agent];
    }

    private function requestImage(object $model, array $payload): array
    {
        $apiKey = KeyVault::decrypt((string)($model->api_key_enc ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('图片模型未配置 API Key');
        }

        $baseUri = rtrim((string)($model->endpoint ?: 'https://api.openai.com/v1'), '/');
        $client = new Client([
            'timeout' => 180,
            'connect_timeout' => 20,
            'verify' => !str_starts_with($baseUri, 'http://localhost') && !str_starts_with($baseUri, 'http://127.0.0.1'),
        ]);

        $response = $client->post($baseUri . '/images/generations', [
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => (string)$model->model_code,
                'prompt' => $payload['prompt'],
                'size' => $payload['size'],
                'n' => 1,
                'response_format' => 'b64_json',
            ],
        ]);

        $body = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('图片模型请求失败: HTTP ' . $statusCode . ' ' . mb_substr($body, 0, 500));
        }

        $decoded = json_decode($body, true);
        if (!\is_array($decoded)) {
            throw new RuntimeException('图片模型返回不可解析');
        }

        return $decoded;
    }

    private function persistImageResponse(array $response, array $payload): array
    {
        $filename = $this->buildFilename($payload);
        $item = $this->extractImageItem($response);
        $uploadService = new UploadService();

        $base64 = (string)($item['b64_json'] ?? $item['result'] ?? '');
        if ($base64 !== '') {
            return $uploadService->uploadContent(
                $this->decodeBase64Image($base64),
                UploadConfigEnum::FOLDER_CINE_KEYFRAMES,
                $filename,
                date('Ymd')
            );
        }

        $url = (string)($item['url'] ?? $item['image_url'] ?? '');
        if ($url !== '') {
            return $uploadService->uploadFromUrl(
                $url,
                UploadConfigEnum::FOLDER_CINE_KEYFRAMES,
                $filename,
                date('Ymd')
            );
        }

        throw new RuntimeException('图片模型未返回 b64_json、result 或 url');
    }

    private function extractImageItem(array $response): array
    {
        $item = $response['data'][0] ?? null;
        if (!\is_array($item)) {
            $item = $this->extractResponsesImageItem($response);
        }

        if (!\is_array($item)) {
            throw new RuntimeException('图片模型未返回可识别的图片内容');
        }

        return $item;
    }

    private function extractResponsesImageItem(array $response): ?array
    {
        foreach (($response['output'] ?? []) as $output) {
            if (!\is_array($output)) {
                continue;
            }

            if (($output['type'] ?? '') === 'image_generation_call' && !empty($output['result'])) {
                return ['result' => $output['result']];
            }

            foreach (($output['content'] ?? []) as $content) {
                if (!\is_array($content)) {
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

        return null;
    }

    private function decodeBase64Image(string $base64): string
    {
        if ($base64 !== '') {
            if (str_contains($base64, ',')) {
                $base64 = substr($base64, strpos($base64, ',') + 1);
            }
            $content = base64_decode($base64, true);
            if ($content === false || $content === '') {
                throw new RuntimeException('图片 base64 解码失败');
            }
            return $content;
        }

        throw new RuntimeException('图片 base64 为空');
    }

    private function extractRevisedPrompt(array $response): ?string
    {
        $item = $response['data'][0] ?? null;
        if (!\is_array($item)) {
            return null;
        }

        return isset($item['revised_prompt']) ? (string)$item['revised_prompt'] : null;
    }

    private function buildFilename(array $payload): string
    {
        $projectId = (int)($payload['project_id'] ?? 0);
        $assetId = (int)($payload['asset_id'] ?? 0);
        $shotId = preg_replace('/[^\w\-]/', '_', (string)$payload['shot_id']) ?: 'S01';

        return sprintf('cine_%d_%d_%s_%s.png', $projectId, $assetId, $shotId, date('His'));
    }
}

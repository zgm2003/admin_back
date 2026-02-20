<?php

namespace app\lib;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use app\enum\CacheTTLEnum;
use support\Cache;

class OcrSdk
{
    const CACHE_KEY_TOKEN = 'baidu_ocr_access_token';

    const MAX_IMAGE_SIZE = 4 * 1024 * 1024; // 4MB，百度OCR限制
    const CONCURRENCY = 5; // 并发数

    private Client $client;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 30]);
    }

    /**
     * 单张OCR识别
     */
    public function ocr(string $imageUrl): array
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('无法获取百度 API access_token');
            }

            $encodedImage = $this->downloadAndEncode($imageUrl);

            $url = 'https://aip.baidubce.com/rest/2.0/ocr/v1/general_basic?access_token=' . $accessToken;
            $response = $this->client->post($url, [
                'form_params' => ['image' => $encodedImage],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['words_result'])) {
                return [
                    'msg'  => 'success',
                    'text' => $this->extractText($result),
                    'code' => 200,
                ];
            }

            throw new \Exception('OCR识别失败: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            $this->log('OCR识别失败', ['error' => $e->getMessage(), 'image_url' => $imageUrl]);
            return [
                'msg'   => 'OCR识别失败',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 批量OCR识别（并发）
     */
    public function ocrList(array $imageUrls): array
    {
        if (empty($imageUrls)) {
            return ['msg' => 'success', 'results' => [], 'code' => 200];
        }

        // 单张直接走同步
        if (count($imageUrls) === 1) {
            return ['msg' => 'success', 'results' => [$this->ocr($imageUrls[0])], 'code' => 200];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['msg' => 'error', 'error' => '无法获取百度 API access_token'];
        }

        $url = 'https://aip.baidubce.com/rest/2.0/ocr/v1/general_basic?access_token=' . $accessToken;
        $results = array_fill(0, count($imageUrls), null);

        // 构建并发请求
        $requests = function () use ($imageUrls, $url) {
            foreach ($imageUrls as $index => $imageUrl) {
                try {
                    $encodedImage = $this->downloadAndEncode($imageUrl);
                    yield $index => new Request('POST', $url, [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ], http_build_query(['image' => $encodedImage]));
                } catch (\Exception $e) {
                    $this->log('图片下载失败', ['error' => $e->getMessage(), 'image_url' => $imageUrl]);
                    // yield nothing, 在 results 中保持 null
                }
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => self::CONCURRENCY,
            'fulfilled' => function ($response, $index) use (&$results) {
                $body = json_decode($response->getBody()->getContents(), true);
                if (isset($body['words_result'])) {
                    $results[$index] = [
                        'msg'  => 'success',
                        'text' => $this->extractText($body),
                        'code' => 200,
                    ];
                } else {
                    $results[$index] = [
                        'msg'   => 'OCR识别失败',
                        'error' => json_encode($body, JSON_UNESCAPED_UNICODE),
                    ];
                }
            },
            'rejected' => function ($reason, $index) use (&$results, $imageUrls) {
                $msg = $reason instanceof \Exception ? $reason->getMessage() : (string)$reason;
                $this->log('OCR请求失败', ['error' => $msg, 'image_url' => $imageUrls[$index] ?? '']);
                $results[$index] = [
                    'msg'   => 'OCR识别失败',
                    'error' => $msg,
                ];
            },
        ]);

        $pool->promise()->wait();

        // 填充跳过的结果
        foreach ($results as $i => $r) {
            if ($r === null) {
                $results[$i] = ['msg' => 'OCR识别失败', 'error' => '图片下载失败'];
            }
        }

        return ['msg' => 'success', 'results' => $results, 'code' => 200];
    }

    /**
     * 获取百度access_token（带Redis缓存）
     */
    private function getAccessToken(): string|false
    {
        // 先从缓存取
        $cached = Cache::get(self::CACHE_KEY_TOKEN);
        if ($cached) {
            return $cached;
        }

        $apiKey    = getenv('BAIDU_API_KEY');
        $secretKey = getenv('BAIDU_SECRET_KEY');

        if (!$apiKey || !$secretKey) {
            $this->log('百度OCR配置缺失', ['api_key' => $apiKey ? '已配置' : '未配置']);
            return false;
        }

        try {
            $response = $this->client->get('https://aip.baidubce.com/oauth/2.0/token', [
                'query' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $apiKey,
                    'client_secret' => $secretKey,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $token = $data['access_token'] ?? false;

            if ($token) {
                Cache::set(self::CACHE_KEY_TOKEN, $token, CacheTTLEnum::SINGLE_SESSION_POINTER);
            }

            return $token;
        } catch (\Exception $e) {
            $this->log('获取百度access_token失败', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 下载图片并Base64编码，带大小检查
     */
    private function downloadAndEncode(string $imageUrl): string
    {
        $response = $this->client->get($imageUrl);
        $content = $response->getBody()->getContents();

        if (strlen($content) > self::MAX_IMAGE_SIZE) {
            throw new \Exception('图片超过4MB限制: ' . $imageUrl);
        }

        return base64_encode($content);
    }

    /**
     * 提取识别文字
     */
    private function extractText(array $result): string
    {
        $lines = [];
        foreach ($result['words_result'] as $item) {
            $lines[] = $item['words'];
        }
        return implode("\n", $lines);
    }

    /**
     * 记录日志
     */
    private function log(string $msg, array $context = []): void
    {
        $logger = log_daily("OCR");
        $logger->info($msg, $context);
    }
}

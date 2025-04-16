<?php

namespace app\lib;

use GuzzleHttp\Client;

class OcrSdk
{
    // 构造函数
    public function __construct()
    {
        // 初始化可以放到这里
    }

    /**
     * OCR 识别
     *
     * @param string $imageUrl 图片 URL
     * @return array 返回识别结果或错误信息
     */
    public function Ocr($imageUrl)
    {
        try {
            // 获取百度 API 的 access_token
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('无法获取百度 API access_token');
            }

            // 使用 Guzzle 下载图片并进行 Base64 编码
            $client = new Client();
            $imageContent = $client->get($imageUrl)->getBody()->getContents();
            $encodedImage = base64_encode($imageContent);

            // 准备请求体
            $bodys = [
                'image' => $encodedImage
            ];

            // 发起 POST 请求到百度 OCR API
            $url = 'https://aip.baidubce.com/rest/2.0/ocr/v1/webimage?access_token=' . $accessToken;
            $response = $this->request_post($url, $bodys);

            // 解析返回的 JSON 数据
            $result = json_decode($response, true);

            if (isset($result['words_result'])) {
                return [
                    'msg'  => 'success',
                    'text' => $this->extractTextFromResult($result),
                    'code' => 200,
                ];
            } else {
                throw new \Exception('OCR 识别失败，返回结果为空');
            }
        } catch (\Exception $e) {
            // 记录异常日志
            $this->log('OCR 识别失败', [
                'error'     => $e->getMessage(),
                'image_url' => $imageUrl,
            ]);

            return [
                'msg'   => 'OCR 识别失败',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 批量 OCR 识别
     *
     * @param array $imageUrls 图片 URL 数组
     * @return array 返回识别结果或错误信息
     */
    public function ocrList(array $imageUrls)
    {
        $results = [];

        foreach ($imageUrls as $imageUrl) {
            $results[] = $this->Ocr($imageUrl);
        }

        return [
            'msg'     => 'success',
            'results' => $results,
            'code'    => 200,
        ];
    }

    /**
     * 获取百度 API access_token
     *
     * @return string|false 返回 access token 或 false
     */
    private function getAccessToken()
    {
        $apiKey    = getenv('BAIDU_API_KEY');
        $secretKey = getenv('BAIDU_SECRET_KEY');

        // 使用 Guzzle 请求鉴权 API 获取 token
        $client   = new Client();
        $response = $client->get('https://aip.baidubce.com/oauth/2.0/token', [
            'query' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $apiKey,
                'client_secret' => $secretKey,
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['access_token'] ?? false;
    }

    /**
     * 发起 HTTP POST 请求
     *
     * @param string $url 请求的 URL
     * @param array $param 请求的参数
     * @return mixed
     */
    private function request_post($url, $param)
    {
        $client = new Client(); // 创建 GuzzleHttp 客户端

        try {
            // 发送 POST 请求
            $response = $client->post($url, [
                'form_params' => $param,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            // 获取响应体
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            throw new \Exception('HTTP 请求失败: ' . $e->getMessage());
        }
    }

    /**
     * 提取识别的文字结果
     *
     * @param array $result 百度 OCR 返回的结果
     * @return string 提取的文字
     */
    private function extractTextFromResult($result)
    {
        $text = '';
        foreach ($result['words_result'] as $item) {
            $text .= $item['words'];
        }
        return $text;
    }

    /**
     * 记录日志
     *
     * @param string $msg 日志信息
     * @param array $context 日志上下文
     */
    private function log($msg, $context = [])
    {
        $logger = log_daily("OCR"); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
}

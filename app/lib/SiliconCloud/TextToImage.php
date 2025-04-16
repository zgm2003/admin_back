<?php

namespace app\lib\SiliconCloud;

use GuzzleHttp\Client;

class TextToImage
{
    /**
     * 调用 SiliconCloud 图像生成 API
     *
     * @param string      $model              模型名称，例如 "Kwai-Kolors/Kolors"
     * @param string      $prompt             正向提示词，描述生成图像要求的内容
     * @param string      $imageSize          输出图像分辨率，例如 "1024x1024"
     * @param int         $batchSize          批次数量，默认为 1
     * @param int         $seed               随机数种子（可选）
     * @param int         $numInferenceSteps  生成推理步数，默认 20
     * @param float       $guidanceScale      引导尺度，默认 7.5
     * @param string|null $referenceImage     参考图，如果传入的是 URL，会自动转换为 Base64 格式（附带 data:image/png;base64, 前缀），可选
     *
     * @return array 统一返回结果，包含 data/code/msg
     */
    public static function generateImage(
        string $model,
        string $prompt,
        string $imageSize = "1024x1024",
        int $batchSize = 1,
        int $seed = 0,
        int $numInferenceSteps = 20,
        float $guidanceScale = 7.5,
        ?string $referenceImage = null
    ) {
        $token = getenv('SILICONFLOW_API_TOKEN');
        $url = "https://api.siliconflow.cn/v1/images/generations";

        // 构造请求数据
        $payload = [
            "model"               => $model,
            "prompt"              => $prompt,
            "image_size"          => $imageSize,
            "batch_size"          => $batchSize,
            "seed"                => $seed,
            "num_inference_steps" => $numInferenceSteps,
            "guidance_scale"      => $guidanceScale,
        ];

        // 如果传入了参考图，则判断是否为 URL
        if ($referenceImage) {
            if (filter_var($referenceImage, FILTER_VALIDATE_URL)) {
                // URL 格式，则获取图片并转换成 Base64
                $base64Image = self::getImageBase64($referenceImage);
                if ($base64Image) {
                    $payload["image"] = $base64Image;
                } else {
                    self::log("参考图 URL 转 Base64 编码失败，参考图参数将被忽略。");
                }
            } else {
                // 不是 URL，假设已是 Base64 格式
                $payload["image"] = $referenceImage;
            }
        }


        $client = new Client();
        try {
            $response = $client->request('POST', $url, [
                'headers' => [
                    "Authorization" => "Bearer " . $token,
                    "Content-Type"  => "application/json",
                ],
                'json' => $payload,
            ]);
        } catch (\Exception $e) {
            self::log("Request exception: " . $e->getMessage());
            return self::response([], "请求失败，异常信息：" . $e->getMessage(), 500);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            self::log("Request Failed, status code = " . $statusCode);
            return self::response([], "请求失败，错误代码：" . $statusCode, $statusCode);
        }

        $bodyContent = $response->getBody()->getContents();
        self::log("Response: " . $bodyContent);
        $data = json_decode($bodyContent, true);

        return self::response($data, "图像生成成功", 200);
    }

    /**
     * 根据 URL 获取图片内容并转换为 Base64 编码格式（附带 data:image/png;base64, 前缀）
     *
     * @param string $url
     * @return string|null
     */
    private static function getImageBase64(string $url): ?string
    {
        try {
            $imageContent = file_get_contents($url);
            if ($imageContent === false) {
                self::log("无法获取图片内容，URL: " . $url);
                return null;
            }
            $base64 = base64_encode($imageContent);
            return "data:image/png;base64," . $base64;
        } catch (\Exception $e) {
            self::log("获取或转换参考图失败，错误信息: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 统一返回结果格式
     *
     * @param mixed  $data
     * @param string $msg
     * @param int    $code
     *
     * @return array
     */
    public static function response($data = [], string $msg = 'success', int $code = 0)
    {
        return [
            'data' => $data,
            'code' => $code,
            'msg'  => $msg,
        ];
    }

    /**
     * 日志记录方法
     *
     * @param string $msg
     * @param array  $context
     */
    private static function log($msg, $context = [])
    {
        $logger = log_daily("SiliconCloud_TextToImage"); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
}

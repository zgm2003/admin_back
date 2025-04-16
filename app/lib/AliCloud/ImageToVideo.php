<?php

namespace app\lib\AliCloud;

use GuzzleHttp\Client;

class ImageToVideo
{
    /**
     * 发起视频合成任务（异步）
     *
     * @param string $model  模型名称，例如 "wanx2.1-i2v-turbo" 或 "wanx2.1-i2v-plus"
     * @param string $prompt 文本提示词，支持中英文，长度不超过800字符
     * @param string $imgUrl 用于生成视频的第一帧图像 URL，支持 JPEG、JPG、PNG、BMP、WEBP 格式
     * @param array  $parameters 视频处理参数，包括：
     *                             - resolution: "480P" 或 "720P"，默认 "720P"
     *                             - duration: 视频时长（单位秒），例如 3、4、5；plus 模型仅支持5秒
     *                             - prompt_extend: bool 是否开启 prompt 智能改写，默认 true
     *                             - seed: 随机数种子（可选）
     *
     * @return array 请求返回的数据格式：
     *               成功返回 ["data" => 任务ID, "code" => 200, "msg" => "视频生成中，请稍后查看"]
     *               失败返回相应错误信息
     */
    public static function createTask(string $model, string $prompt, string $imgUrl, array $parameters = [])
    {
        $apiKey = getenv('AIGC_API_KEY');
        $url    = "https://dashscope.aliyuncs.com/api/v1/services/aigc/video-generation/video-synthesis";

        $requestBody = [
            "model"  => $model,
            "input"  => [
                "prompt"  => $prompt,
                "img_url" => $imgUrl,
            ],
            "parameters" => $parameters,
        ];

        self::log("CreateTask Request Body: " . json_encode($requestBody));

        $client = new Client(['verify' => false]);
        try {
            $response = $client->request('POST', $url, [
                'headers' => [
                    "Content-Type"      => "application/json",
                    "Authorization"     => "Bearer " . $apiKey,
                    "X-DashScope-Async" => "enable",
                ],
                'json' => $requestBody,
            ]);
        } catch (\Exception $e) {
            self::log("Create task exception: " . $e->getMessage());
            return self::response([], "请求失败，异常信息：" . $e->getMessage(), 500);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            self::log("Create task failure, status code = " . $statusCode);
            return self::response([], "请求失败，错误代码：" . $statusCode, $statusCode);
        }

        $bodyContent = $response->getBody()->getContents();
        $data        = json_decode($bodyContent, true);

        if (isset($data["output"]) && isset($data["output"]["task_id"])) {
            $taskId = $data["output"]["task_id"];
            return self::response($taskId, "视频生成中，请稍后查看", 200);
        } else {
            self::log("Create task error: code=" . ($data["code"] ?? '') . "; message=" . ($data["message"] ?? ''));
            return self::response([], "请求错误: " . ($data["message"] ?? '未知错误'), $data["code"] ?? 500);
        }
    }

    /**
     * 根据任务ID轮询查询任务执行结果
     *
     * @param string $apiKey
     * @param string $taskId
     *
     * @return mixed|null 任务成功时返回结果详情，失败或超时返回 null
     */
    private static function waitLoopForResult(string $apiKey, string $taskId)
    {
        $baseUrl = "https://dashscope.aliyuncs.com/api/v1/tasks/{$taskId}";
        $client  = new Client(['verify' => false]);

        while (true) {
            try {
                $response = $client->request('GET', $baseUrl, [
                    'headers' => [
                        "Content-Type"  => "application/json",
                        "Authorization" => "Bearer " . $apiKey,
                    ],
                ]);
            } catch (\Exception $e) {
                self::log("Polling request exception: " . $e->getMessage());
                return null;
            }

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                self::log("Polling request failure, status code = " . $statusCode);
                return null;
            }

            $bodyContent = $response->getBody()->getContents();
            $data        = json_decode($bodyContent, true);
            if (isset($data["output"])) {
                $status = $data["output"]["task_status"] ?? "UNKNOWN";
                self::log("Polling task_id={$taskId}, status={$status}");

                if ($status === "SUCCEEDED") {
                    // 任务成功返回详细输出，包括视频 URL 等信息
                    return $data["output"];
                } elseif ($status === "FAILED") {
                    self::log("Task failed, task_id = {$taskId}");
                    return null;
                }
                // 状态可能处于 PENDING、RUNNING、SUSPENDED 或 UNKNOWN，继续轮询
            } else {
                self::log("Unexpected response format: " . json_encode($data));
                return null;
            }

            // 根据实际情况调整轮询间隔（例如：10秒）
            sleep(10);
        }
    }

    /**
     * 获取任务状态（适用于非轮询模式下单次查询）
     *
     * @param string $taskId
     *
     * @return array
     */
    public function getTaskStatus(string $taskId)
    {
        $baseUrl = "https://dashscope.aliyuncs.com/api/v1/tasks/{$taskId}";
        $apiKey  = getenv('AIGC_API_KEY');
        $client  = new Client(['verify' => false]);

        try {
            $response = $client->request('GET', $baseUrl, [
                'headers' => [
                    "Content-Type"  => "application/json",
                    "Authorization" => "Bearer " . $apiKey,
                ],
            ]);
        } catch (\Exception $e) {
            return self::response([], "视频生成失败", 500);
        }

        $bodyContent = $response->getBody()->getContents();
        $data        = json_decode($bodyContent, true);
        if (isset($data['output']) && isset($data['output']['task_status'])) {
            return self::response($data['output'], "视频生成中，请稍后查看", 200);
        }
        return self::response([], "视频生成失败", 500);
    }

    /**
     * 统一日志记录方法，采用 webman 全局 logger()
     *
     * @param string $msg
     * @param array  $context
     */
    private static function log($msg, $context = [])
    {
        $logger = log_daily("AliCloud_ImageToVideo"); // 获取 Logger 实例
        $logger->info($msg, $context);
    }

    /**
     * 统一返回结果格式
     *
     * @param mixed  $data 返回数据
     * @param string $msg  提示信息
     * @param int    $code 状态码
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
}

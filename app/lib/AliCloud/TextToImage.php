<?php

namespace app\lib\AliCloud;

use GuzzleHttp\Client;

class TextToImage
{
    /**
     * 发起图像合成任务（异步）
     *
     * @param string $model 模型名称，例如 "wanx2.1-t2i-turbo"
     * @param string $prompt 正向提示词
     * @param string|null $negativePrompt 反向提示词（目前未使用）
     * @param string $size 输出图像分辨率，例如 "1024*1024"
     * @param int $n 生成图片数量
     *
     * @return array
     */
    public static function createTask(string $model, string $prompt, string $size = "1024*1024", int $n = 1, $seed = null)
    {
        $apiKey = getenv('AIGC_API_KEY');  // webman 下也可以用 getenv() 获取环境变量
        $url = "https://dashscope.aliyuncs.com/api/v1/services/aigc/text2image/image-synthesis";

        // 构造请求体
        $requestBody = [
            "model"      => $model,
            "input"      => [
                "prompt" => $prompt,
            ],
            "parameters" => [
                "size" => $size,
                "n"    => $n,
                "seed" => $seed,
            ]
        ];

        self::log("CreateTask Request Body: " . json_encode($requestBody));

        $client = new Client(['verify' => false]);  // 如需验证证书，可移除 verify 设置
        try {
            $response = $client->request('POST', $url, [
                'headers' => [
                    "Content-Type"     => "application/json",
                    "Authorization"    => "Bearer " . $apiKey,
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
        self::log("Create task response body: " . $bodyContent);
        $data = json_decode($bodyContent, true);
        if (isset($data["output"]) && isset($data["output"]["task_id"])) {
            $taskId     = $data["output"]["task_id"];
            $taskStatus = $data["output"]["task_status"] ?? '';
            $requestId  = $data["request_id"] ?? '';
            self::log("Task created, task_id = {$taskId}, task_status = {$taskStatus}");

            // 通过 task_id 轮询查询任务结果
            $result = self::waitLoopForResult($apiKey, $taskId);
            if ($result) {
                return self::response($result, "图像生成成功", 200);
            } else {
                return self::response([], "图像生成失败", 500);
            }
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
     * @return mixed|null 任务成功返回结果数据，失败或超时返回 null
     */
    private static function waitLoopForResult(string $apiKey, string $taskId)
    {
        $baseUrl = "https://dashscope.aliyuncs.com/api/v1/tasks/{$taskId}";
        $client = new Client(['verify' => false]);

        while (true) {
            try {
                $response = $client->request('GET', $baseUrl, [
                    'headers' => [
                        "Content-Type"  => "application/json",
                        "Authorization" => "Bearer " . $apiKey,
                    ]
                ]);
            } catch (\Exception $e) {
                self::log("Polling exception: " . $e->getMessage());
                return null;
            }

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                self::log("Polling request failure, status code = " . $statusCode);
                return null;
            }

            $bodyContent = $response->getBody()->getContents();
            self::log("Polling response body for task_id={$taskId}: " . $bodyContent);
            $data = json_decode($bodyContent, true);
            if (isset($data["output"])) {
                $status = $data["output"]["task_status"] ?? "UNKNOWN";
                self::log("Polling task_id={$taskId}, status={$status}");

                if ($status === "SUCCEEDED") {
                    // 任务成功返回结果详情
                    return $data["output"];
                } elseif ($status === "FAILED") {
                    self::log("Task failed, task_id = {$taskId}");
                    return null;
                }
                // 状态可能为 PENDING、RUNNING、SUSPENDED 或 UNKNOWN，继续轮询
            } else {
                self::log("Unexpected response format: " . json_encode($data));
                return null;
            }

            // 每 10 秒轮询一次
            sleep(10);
        }
    }

    /**
     * 统一日志记录
     *
     * @param string $msg
     * @param array  $context
     */
    private static function log($msg, $context = [])
    {
        $logger = log_daily("AliCloud_TextToImage"); // 获取 Logger 实例
        $logger->info($msg, $context);
    }

    /**
     * 统一返回格式
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
}

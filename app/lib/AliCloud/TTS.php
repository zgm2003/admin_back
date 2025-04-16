<?php

namespace app\lib\AliCloud;

use app\dep\AiWorkLine\E_commerce\AccountDep;
use app\enum\AccountEnum;
use GuzzleHttp\Client;

class TTS
{
    public $appkey;
    public $token;

    public function __construct()
    {
        $this->appkey = getenv('ALIBABA_CLOUD_APP_KEY');
        $this->token = $this->getAccessToken();
    }

    /**
     * 异步 TTS 请求
     *
     * @param string $text         合成文本
     * @param int    $sampleRate   采样率，例如 16000
     * @param string $voice        语音角色，默认 'xiaoyun'
     * @param int    $speechRate   语速，默认 0
     * @param int    $pitchRate    语调，默认 0
     * @param int    $volume       音量，默认 50
     *
     * @return array 返回格式化数组
     */
    public function TTSAsync($text, $sampleRate, $voice = 'xiaoyun', $speechRate = 0, $pitchRate = 0, $volume = 50)
    {
        $appkey = $this->appkey;
        $token  = $this->token;
        $url    = "https://nls-gateway-cn-shanghai.aliyuncs.com/rest/v1/tts/async";

        // 构造请求体
        $requestBody = [
            'context' => [
                'device_id' => 'my_device_id'
            ],
            'header' => [
                'appkey' => $appkey,
                'token'  => $token,
            ],
            'payload' => [
                'enable_notify' => true,
                'notify_url'    => 'http://123.com',
                'tts_request'   => [
                    'text'            => $text,
                    'format'          => 'wav',
                    'voice'           => $voice,
                    'sample_rate'     => $sampleRate,
                    'speech_rate'     => $speechRate,
                    'pitch_rate'      => $pitchRate,
                    'volume'          => $volume,
                    'enable_subtitle' => false,
                ]
            ]
        ];

        $this->log("AliCloud TTS Async request body: " . json_encode($requestBody));

        // 发送请求
        $client = new Client();
        try {
            $response = $client->request('POST', $url, [
                'headers' => ["Content-Type" => "application/json"],
                'json'    => $requestBody,
            ]);
        } catch (\Exception $e) {
            $this->log("TTS Async request exception: " . $e->getMessage());
            return $this->response([], "请求失败，异常信息：" . $e->getMessage(), 500);
        }

        if ($response->getStatusCode() !== 200) {
            $this->log("TTS request failure, error code = " . $response->getStatusCode());
            return $this->response([], "请求失败，错误代码：" . $response->getStatusCode(), $response->getStatusCode());
        }

        $data = json_decode($response->getBody()->getContents(), true);
        if (isset($data["error_code"]) && $data["error_code"] == 20000000) {
            $task_id    = $data["data"]["task_id"];
            $request_id = $data["request_id"];
            $this->log("Request Success! task_id = " . $task_id);
            $this->log("Request Success! request_id = " . $request_id);

            // 轮询查询状态，返回音频地址
            $audioAddress = $this->waitLoop4Complete($url, $appkey, $token, $task_id, $request_id);
            if ($audioAddress) {
                return $this->response($audioAddress, "语音合成成功", 200);
            } else {
                return $this->response([], "语音合成失败", 500);
            }
        } else {
            $errorCode    = $data["error_code"] ?? '';
            $errorMessage = $data["error_message"] ?? '未知错误';
            $this->log("Request Error: error_code={$errorCode}; error_message={$errorMessage}");
            return $this->response([], "请求错误: " . $errorMessage, $errorCode ?: 500);
        }
    }

    /**
     * 轮询查询任务状态，直至完成返回音频地址
     *
     * @param string $url
     * @param string $appkey
     * @param string $token
     * @param string $task_id
     * @param string $request_id
     *
     * @return mixed 返回音频地址字符串，失败返回 null
     */
    private function waitLoop4Complete($url, $appkey, $token, $task_id, $request_id)
    {
        $fullUrl = "$url?appkey=$appkey&task_id=$task_id&token=$token&request_id=$request_id";
        $client  = new Client();

        while (true) {
            try {
                $response = $client->request('GET', $fullUrl);
            } catch (\Exception $e) {
                $this->log("TTS loop request exception: " . $e->getMessage());
                return null;
            }

            if ($response->getStatusCode() !== 200) {
                $this->log("TTS request failure, error code = " . $response->getStatusCode());
                return null;
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data["error_code"]) && $data["error_code"] == 20000000) {
                $audioAddress = $data["data"]["audio_address"] ?? null;
                if ($audioAddress) {
                    $this->log("TTS Finished! task_id = " . $task_id);
                    $this->log("TTS Finished! audio_address = " . $audioAddress);
                    return $audioAddress;
                }
            }

            // 等待 10 秒后再次轮询
            sleep(10);
        }
    }

    /**
     * 同步 TTS：调用阿里云 TTS 接口，直接返回音频数据，用于即时试听
     *
     * @param string $text         合成文本
     * @param int    $sampleRate   采样率，例如 16000
     * @param string $voice        语音角色，默认 'xiaoyun'
     * @param int    $speechRate   语速，默认 0
     * @param int    $pitchRate    语调，默认 0
     * @param int    $volume       音量，默认 50
     *
     * @return mixed 返回音频二进制数据，失败返回错误信息字符串
     */
    public function TssSync($text, $sampleRate, $voice = 'xiaoyun', $speechRate = 0, $pitchRate = 0, $volume = 50)
    {
        $appkey = $this->appkey;
        $token  = $this->token;
        $url    = "https://nls-gateway-cn-shanghai.aliyuncs.com/stream/v1/tts";
        $taskArr = [
            "appkey"      => $appkey,
            "token"       => $token,
            "text"        => $text,
            "format"      => 'wav',
            "sample_rate" => $sampleRate,
            "voice"       => $voice,
            "speech_rate" => $speechRate,
            "pitch_rate"  => $pitchRate,
            "volume"      => $volume,
        ];

        $this->log("AliCloud TTS Sync request body: " . json_encode($taskArr));

        $client = new Client();
        try {
            $response = $client->request('POST', $url, [
                'headers' => [
                    "Content-Type" => "application/json",
                ],
                'json' => $taskArr,
            ]);
        } catch (\Exception $e) {
            $this->log("TTS Sync request exception: " . $e->getMessage());
            return $e->getMessage();
        }

        $contentType = $response->getHeaderLine('Content-Type');

        if (stripos($contentType, "audio/mpeg") !== false) {
            $this->log("AliCloud TTS Sync succeeded. Audio data received.");
            // 返回音频二进制数据
            return $response->getBody()->getContents();
        } else {
            $failContent = $response->getBody()->getContents();
            $this->log("AliCloud TTS Sync failed: " . $failContent);
            return $failContent;
        }
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
    public function response($data = [], $msg = 'success', $code = 0)
    {
        return [
            'data' => $data,
            'code' => $code,
            'msg'  => $msg
        ];
    }

    /**
     * 获取 Access Token
     *
     * @return mixed
     */
    private function getAccessToken()
    {
        $accountDep = new AccountDep();
        $resAccount = $accountDep->firstByPlatform(AccountEnum::ALICLOUD);
        return $resAccount->token;
    }

    /**
     * 日志记录
     *
     * @param string $msg
     * @param array  $context
     *
     * @return void
     */
    private function log($msg, $context = [])
    {
        $logger = log_daily("AliCloud_TTS"); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
}

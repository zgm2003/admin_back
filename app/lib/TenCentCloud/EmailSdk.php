<?php

namespace app\lib\TenCentCloud;

use TencentCloud\Common\Credential;
use TencentCloud\Ses\V20201002\Models\SendEmailRequest;
use TencentCloud\Ses\V20201002\SesClient;

class EmailSdk
{
    private $sesClient;

    public function __construct()
    {
        $secretId = getenv('TENCENTCLOUD_SECRET_ID');
        $secretKey = getenv('TENCENTCLOUD_SECRET_KEY');
        $region = "ap-guangzhou"; // 默认区域是广州

        // 初始化腾讯云 SES 客户端
        $cred = new Credential($secretId, $secretKey);
        $this->sesClient = new SesClient($cred, $region);
    }

    public function email($toEmail, $theme,$code)
    {
        try {
            // 构建发送邮件请求
            $request = new SendEmailRequest();
            $request->FromEmailAddress = getenv('MAIL_FROM_ADDRESS'); // 发件人地址
            $request->Destination = [$toEmail]; // 收件人地址
            $request->Subject = $theme; // 邮件主题
            $request->Template = [
                "TemplateID" => 31463, // 模板 ID
                "TemplateData" => json_encode(["code" => $code]) // 模板参数
            ];

            // 调用发送接口
            $response = $this->sesClient->SendEmail($request);
            $this->log("emailSuccess", ['toEmail' => $toEmail, 'code' => $code, 'response' => $response->toJsonString()]);
        } catch (\Exception $e) {
            $this->log("emailFail", ['toEmail' => $toEmail, 'code' => $code, 'error' => $e->getMessage()]);
        }
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("email"); // 获取 Logger 实例
        $logger->info($msg, $context); // 调用 Logger 实例的 info 方法
    }

}

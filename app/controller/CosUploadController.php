<?php

namespace app\controller;

use support\Request;
use TencentCloud\Sts\V20180813\Models\GetFederationTokenRequest;
use TencentCloud\Sts\V20180813\StsClient;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use Webman\Http\Response;

class CosUploadController extends Controller
{
    public function getUploadToken(Request $request)
    {
        $folder = $request->input('folderName');
        if (!$folder || !is_string($folder)) {
            return response(json_encode(['message' => 'folderName 不能为空且必须为字符串']),500);
        }

        $secretId = getenv('TENCENTCLOUD_SECRET_ID');
        $secretKey = getenv('TENCENTCLOUD_SECRET_KEY');
        $bucket = getenv('COS_BUCKET');
        $region = getenv('COS_REGION');
        $appid = getenv('COS_APP_ID'); // 请确保 .env 中已配置 COS_APP_ID

        $cred = new Credential($secretId, $secretKey);
        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint("sts.tencentcloudapi.com");

        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);

        $client = new StsClient($cred, $region, $clientProfile);

        $policy = [
            'version' => '2.0',
            'statement' => [[
                'action' => [
                    'cos:PutObject',
                    'cos:PostObject'
                ],
                'effect' => 'allow',
                'principal' => ['qcs' => ['*']],
                'resource' => [
                    "qcs::cos:{$region}:uid/{$appid}:{$bucket}/{$folder}/*"
                ]
            ]]
        ];

        $params = [
            "DurationSeconds" => 1800,
            "Name" => "upload-session",
            "Policy" => json_encode($policy)
        ];

        try {
            $req = new GetFederationTokenRequest();
            $req->fromJsonString(json_encode($params));
            $response = $client->GetFederationToken($req);

            return json([
                'credentials' => [
                    'tmpSecretId' => $response->Credentials->TmpSecretId,
                    'tmpSecretKey' => $response->Credentials->TmpSecretKey,
                    'sessionToken' => $response->Credentials->Token,
                ],
                'expiredTime' => $response->ExpiredTime,
                'startTime' => time(),
                'bucket' => $bucket,
                'region' => $region,
                'uploadPath' => "{$folder}/" // 限制前缀
            ]);
        } catch (\Exception $e) {
            return response(json_encode(['message' => '获取临时密钥失败']),500);
        }
    }

}

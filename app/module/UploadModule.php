<?php

namespace app\module;

use app\dep\System\UploadSettingDep;
use app\lib\Crypto\KeyVault;
use AlibabaCloud\Client\AlibabaCloud;
use TencentCloud\Sts\V20180813\Models\GetFederationTokenRequest;
use TencentCloud\Sts\V20180813\StsClient;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;

class UploadModule extends BaseModule
{
    private $allowedFolders = [
        'avatar',
        'upload',
        'file',
        'image',
        'article',
        'ai_chat_images'
    ];

    public function getUploadToken($request)
    {
        $folder = trim((string)$request->input('folderName', ''));

        self::throwIf(
            $folder === ''
            || !in_array($folder, $this->allowedFolders, true)
            || str_contains($folder, '..'),
            'folderName 非法'
        );

        $dep = new UploadSettingDep();
        $setting = $dep->getActive();

        self::throwIf(!$setting, '未配置有效的上传设置');

        $data = [];
        if ($setting['driver'] === 'cos') {
            $data = $this->getCosToken($setting, $folder);
        } elseif ($setting['driver'] === 'oss') {
            $data = $this->getOssToken($setting, $folder);
        } else {
            self::throw('不支持的驱动类型');
        }

        // Merge rule info
        $data['rule'] = [
            'maxSize' => (int)$setting['max_size_mb'], // MB
            'imageExts' => json_decode($setting['image_exts'] ?? '[]'),
            'fileExts' => json_decode($setting['file_exts'] ?? '[]'),
        ];

        return self::success($data);
    }

    private function getCosToken($setting, $folder)
    {
        $secretId  = KeyVault::decrypt($setting['secret_id_enc'] ?? '');
        $secretKey = KeyVault::decrypt($setting['secret_key_enc'] ?? '');
        $bucket    = $setting['bucket'];
        $region    = $setting['region'];
        $appid     = $setting['appid'];

        if (!$secretId || !$secretKey || !$bucket || !$region || !$appid) {
            throw new \Exception('COS 配置缺失');
        }

        $cred = new Credential($secretId, $secretKey);
        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint("sts.tencentcloudapi.com");
        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);
        $client = new StsClient($cred, $region, $clientProfile);

        $policy = [
            'version' => '2.0',
            'statement' => [[
                'action' => ['cos:PutObject', 'cos:PostObject'],
                'effect' => 'allow',
                'principal' => ['qcs' => ['*']],
                'resource' => [
                    "qcs::cos:{$region}:uid/{$appid}:{$bucket}/{$folder}/*"
                ],
            ]],
        ];

        $params = [
            "DurationSeconds" => 1800,
            "Name" => "upload-" . date('YmdHis'),
            "Policy" => json_encode($policy, JSON_UNESCAPED_SLASHES),
        ];

        $req = new GetFederationTokenRequest();
        $req->fromJsonString(json_encode($params));
        $response = $client->GetFederationToken($req);

        return [
            'provider' => 'cos',
            'credentials' => [
                'tmpSecretId'  => $response->Credentials->TmpSecretId,
                'tmpSecretKey' => $response->Credentials->TmpSecretKey,
                'sessionToken' => $response->Credentials->Token,
            ],
            'expiredTime'   => (int)$response->ExpiredTime,
            'startTime'     => time(),
            'bucket'        => $bucket,
            'region'        => $region,
            'uploadPath'    => "{$folder}/",
            'bucket_domain' => $setting['bucket_domain'] ?? '',
        ];
    }

    private function getOssToken($setting, $folder)
    {
        $region  = $setting['region'];
        $bucket  = $setting['bucket'];
        $roleArn = $setting['role_arn'];
        $ak      = KeyVault::decrypt($setting['secret_id_enc'] ?? '');
        $sk      = KeyVault::decrypt($setting['secret_key_enc'] ?? '');

        if (!$region || !$bucket || !$roleArn || !$ak || !$sk) {
            throw new \Exception('OSS 配置缺失');
        }

        $duration = 1800;

        AlibabaCloud::accessKeyClient($ak, $sk)
            ->regionId($region)
            ->asDefaultClient();

        $policy = [
            'Version' => '1',
            'Statement' => [[
                'Effect'   => 'Allow',
                'Action'   => ['oss:PutObject', 'oss:PostObject'],
                'Resource' => ["acs:oss:*:*:{$bucket}/{$folder}/*"],
            ]],
        ];

        $res = \AlibabaCloud\Sts\Sts::v20150401()
            ->assumeRole()
            ->withRoleArn($roleArn)
            ->withRoleSessionName('oss-upload-' . date('YmdHis'))
            ->withDurationSeconds($duration)
            ->withPolicy(json_encode($policy, JSON_UNESCAPED_SLASHES))
            ->request();

        $cred = $res->get('Credentials') ?: [];

        $expiredTime = time() + $duration;
        if (!empty($cred['Expiration'])) {
            $ts = strtotime((string)$cred['Expiration']);
            if ($ts !== false) $expiredTime = $ts;
        }

        return [
            'provider' => 'oss',
            'credentials' => [
                'tmpSecretId'  => $cred['AccessKeyId'] ?? null,
                'tmpSecretKey' => $cred['AccessKeySecret'] ?? null,
                'sessionToken' => $cred['SecurityToken'] ?? null,
            ],
            'bucket'     => $bucket,
            'region'     => $region,
            'uploadPath' => "{$folder}/",
            'startTime'  => time(),
            'expiredTime'=> (int)$expiredTime,
        ];
    }
}

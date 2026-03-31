<?php

namespace app\module\System;

use app\enum\UploadConfigEnum;
use app\lib\Crypto\KeyVault;
use app\module\BaseModule;
use app\service\System\UploadService;
use AlibabaCloud\Client\AlibabaCloud;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Sts\V20180813\Models\GetFederationTokenRequest;
use TencentCloud\Sts\V20180813\StsClient;

/**
 * 上传凭证模块
 * 负责：根据当前启用的上传配置，签发 COS/OSS 临时上传凭证
 * 凭证限定目录级权限，有效期 1800 秒
 */
class UploadModule extends BaseModule
{
    /** @var int 临时凭证有效期（秒） */
    private const TOKEN_DURATION = 1800;

    /**
     * 获取上传临时凭证（根据驱动类型分发 COS/OSS）
     */
    public function getUploadToken($request): array
    {
        $folder = \trim((string)$request->input('folderName', ''));

        self::throwIf(
            $folder === ''
            || !\array_key_exists($folder, UploadConfigEnum::$folderArr)
            || \str_contains($folder, '..'),
            'folderName 非法'
        );

        $setting = $this->svc(UploadService::class)->getActiveSettingOrFail();

        $data = match ($setting['driver']) {
            'cos' => $this->getCosToken($setting, $folder),
            'oss' => $this->getOssToken($setting, $folder),
            default => self::throw('不支持的驱动类型'),
        };

        // 合并上传规则信息
        $data['rule'] = [
            'maxSize'   => (int)$setting['max_size_mb'],
            'imageExts' => $setting['image_exts'] ?? [],
            'fileExts'  => $setting['file_exts'] ?? [],
        ];

        return self::success($data);
    }

    /**
     * 签发腾讯云 COS 临时凭证（STS GetFederationToken）
     */
    private function getCosToken(array $setting, string $folder): array
    {
        $secretId  = KeyVault::decrypt($setting['secret_id_enc'] ?? '');
        $secretKey = KeyVault::decrypt($setting['secret_key_enc'] ?? '');
        $bucket    = $setting['bucket'];
        $region    = $setting['region'];
        $appid     = $setting['appid'];

        self::throwIf(!$secretId || !$secretKey || !$bucket || !$region || !$appid, 'COS 配置缺失');

        $cred = new Credential($secretId, $secretKey);
        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint('sts.tencentcloudapi.com');
        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);
        $client = new StsClient($cred, $region, $clientProfile);

        $policy = [
            'version'   => '2.0',
            'statement' => [[
                'action'    => ['cos:PutObject', 'cos:PostObject'],
                'effect'    => 'allow',
                'principal' => ['qcs' => ['*']],
                'resource'  => ["qcs::cos:{$region}:uid/{$appid}:{$bucket}/{$folder}/*"],
            ]],
        ];

        $req = new GetFederationTokenRequest();
        $req->fromJsonString(\json_encode([
            'DurationSeconds' => self::TOKEN_DURATION,
            'Name'            => 'upload-' . \date('YmdHis'),
            'Policy'          => \json_encode($policy, JSON_UNESCAPED_SLASHES),
        ]));
        $response = $client->GetFederationToken($req);

        return [
            'provider'      => 'cos',
            'credentials'   => [
                'tmpSecretId'  => $response->Credentials->TmpSecretId,
                'tmpSecretKey' => $response->Credentials->TmpSecretKey,
                'sessionToken' => $response->Credentials->Token,
            ],
            'expiredTime'   => (int)$response->ExpiredTime,
            'startTime'     => \time(),
            'bucket'        => $bucket,
            'region'        => $region,
            'uploadPath'    => "{$folder}/",
            'bucket_domain' => $setting['bucket_domain'] ?? '',
        ];
    }

    /**
     * 签发阿里云 OSS 临时凭证（STS AssumeRole）
     */
    private function getOssToken(array $setting, string $folder): array
    {
        $region  = $setting['region'];
        $bucket  = $setting['bucket'];
        $roleArn = $setting['role_arn'];
        $ak      = KeyVault::decrypt($setting['secret_id_enc'] ?? '');
        $sk      = KeyVault::decrypt($setting['secret_key_enc'] ?? '');

        self::throwIf(!$region || !$bucket || !$roleArn || !$ak || !$sk, 'OSS 配置缺失');

        AlibabaCloud::accessKeyClient($ak, $sk)
            ->regionId($region)
            ->asDefaultClient();

        $policy = [
            'Version'   => '1',
            'Statement' => [[
                'Effect'   => 'Allow',
                'Action'   => ['oss:PutObject', 'oss:PostObject'],
                'Resource' => ["acs:oss:*:*:{$bucket}/{$folder}/*"],
            ]],
        ];

        $res = \AlibabaCloud\Sts\Sts::v20150401()
            ->assumeRole()
            ->withRoleArn($roleArn)
            ->withRoleSessionName('oss-upload-' . \date('YmdHis'))
            ->withDurationSeconds(self::TOKEN_DURATION)
            ->withPolicy(\json_encode($policy, JSON_UNESCAPED_SLASHES))
            ->request();

        $cred = $res->get('Credentials') ?: [];

        $expiredTime = \time() + self::TOKEN_DURATION;
        if (!empty($cred['Expiration'])) {
            $ts = \strtotime((string)$cred['Expiration']);
            if ($ts !== false) {
                $expiredTime = $ts;
            }
        }

        return [
            'provider'    => 'oss',
            'credentials' => [
                'tmpSecretId'  => $cred['AccessKeyId'] ?? null,
                'tmpSecretKey' => $cred['AccessKeySecret'] ?? null,
                'sessionToken' => $cred['SecurityToken'] ?? null,
            ],
            'bucket'      => $bucket,
            'region'      => $region,
            'uploadPath'  => "{$folder}/",
            'startTime'   => \time(),
            'expiredTime' => (int)$expiredTime,
        ];
    }
}

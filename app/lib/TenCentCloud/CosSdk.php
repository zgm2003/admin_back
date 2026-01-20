<?php

namespace app\lib\TenCentCloud;

use Qcloud\Cos\Client;

/**
 * 腾讯云 COS 对象存储服务
 */
class CosSdk
{
    private Client $client;
    private string $bucket;
    private string $region;
    private string $cdnDomain;

    public function __construct()
    {
        $secretId = getenv('TENCENTCLOUD_SECRET_ID');
        $secretKey = getenv('TENCENTCLOUD_SECRET_KEY');
        $this->region = getenv('COS_REGION') ?: 'ap-nanjing';
        $this->bucket = getenv('COS_BUCKET');
        $this->cdnDomain = getenv('COS_CDN_DOMAIN') ?: '';

        $this->client = new Client([
            'region' => $this->region,
            'schema' => 'https',
            'credentials' => [
                'secretId' => $secretId,
                'secretKey' => $secretKey,
            ],
        ]);
    }

    /**
     * 上传文件到 COS
     * @param string $localPath 本地文件路径
     * @param string $cosPath COS 路径（如 exports/20260120/xxx.xlsx）
     * @return array ['url' => '访问地址', 'size' => 文件大小]
     */
    public function upload(string $localPath, string $cosPath): array
    {
        $result = $this->client->upload(
            $this->bucket,
            $cosPath,
            fopen($localPath, 'rb')
        );

        $fileSize = filesize($localPath);

        // 优先使用 CDN 域名
        if ($this->cdnDomain) {
            $url = 'https://' . $this->cdnDomain . '/' . $cosPath;
        } else {
            $url = $result['Location'] ?? $this->getObjectUrl($cosPath);
        }

        return [
            'url' => $url,
            'size' => $fileSize,
        ];
    }

    /**
     * 删除 COS 文件
     * @param string $cosPath COS 路径
     */
    public function delete(string $cosPath): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $cosPath,
        ]);
    }

    /**
     * 批量删除 COS 文件
     * @param array $cosPaths COS 路径数组
     */
    public function deleteMultiple(array $cosPaths): void
    {
        if (empty($cosPaths)) {
            return;
        }

        $objects = array_map(fn($path) => ['Key' => $path], $cosPaths);

        $this->client->deleteObjects([
            'Bucket' => $this->bucket,
            'Objects' => $objects,
        ]);
    }

    /**
     * 获取文件访问 URL
     */
    public function getObjectUrl(string $cosPath): string
    {
        if ($this->cdnDomain) {
            return 'https://' . $this->cdnDomain . '/' . $cosPath;
        }
        return 'https://' . $this->bucket . '.cos.' . $this->region . '.myqcloud.com/' . $cosPath;
    }
}

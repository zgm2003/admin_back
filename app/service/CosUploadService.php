<?php

namespace app\service;

use Qcloud\Cos\Client;

/**
 * 腾讯云 COS 上传服务
 * 负责：从远程 URL 下载文件并上传到 COS，返回公网访问地址
 */
class CosUploadService
{
    private Client $cosClient;
    private string $bucket;

    public function __construct()
    {
        $this->cosClient = new Client([
            'region'      => getenv('COS_REGION'),
            'credentials' => [
                'secretId'  => getenv('TENCENTCLOUD_SECRET_ID'),
                'secretKey' => getenv('TENCENTCLOUD_SECRET_KEY'),
            ],
        ]);
        $this->bucket = getenv('COS_BUCKET');
    }

    /**
     * 从远程 URL 下载文件并上传到 COS
     *
     * @param string      $imageUrl   远程文件地址
     * @param string|null $folderName 存储目录（如 'ai_image_video'）
     * @return string|null 上传后的 COS 公网地址，失败返回 null
     */
    public function uploadFromUrl(string $imageUrl, string $folderName = 'ai_image_video'): ?string
    {
        $imageData = @file_get_contents($imageUrl);
        if ($imageData === false) {
            return null;
        }

        $fileExt = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $key = "{$folderName}/" . time() . '_' . uniqid() . ".{$fileExt}";

        try {
            $result = $this->cosClient->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'Body'   => $imageData,
                'ACL'    => 'public-read',
            ]);

            return 'https://' . $result['Location'];
        } catch (\Exception $e) {
            log_daily('CosUploadService')->info("COS 上传失败: {$e->getMessage()}");
            return null;
        }
    }
}
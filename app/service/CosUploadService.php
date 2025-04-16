<?php

namespace app\service;

use Qcloud\Cos\Client;

class CosUploadService
{
    protected $cosClient;
    protected $bucket;
    protected $region;

    public function __construct()
    {
        $this->cosClient = new Client([
            'region' => getenv('COS_REGION'),
            'credentials' => [
                'secretId' => getenv('TENCENTCLOUD_SECRET_ID'),
                'secretKey' => getenv('TENCENTCLOUD_SECRET_KEY'),
            ]
        ]);
        $this->bucket = getenv('COS_BUCKET');
        $this->region = getenv('COS_REGION');
    }

    /**
     * 从远程图片 URL 上传到 COS，返回上传后的公网 URL
     *
     * @param string $imageUrl 远程图片地址
     * @param string|null $folderName 存储目录，如 'ai_image_video'
     * @return string|null 上传后的 COS 访问地址，失败返回 null
     */
    public function uploadFromUrl(string $imageUrl, string $folderName = 'ai_image_video'): ?string
    {
        $imageData = @file_get_contents($imageUrl);
        if ($imageData === false) {
            return null; // 下载失败
        }

        $fileExt = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$fileExt) {
            $fileExt = 'jpg'; // 默认后缀
        }

        $fileName = time() . '_' . uniqid() . '.' . $fileExt;
        $key = "{$folderName}/{$fileName}";

        try {
            $result = $this->cosClient->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'Body'   => $imageData,
                'ACL'    => 'public-read',
            ]);

            return 'https://' . $result['Location'];
        } catch (\Exception $e) {
            $this->log("COS 上传失败: " . $e->getMessage());
            return null;
        }
    }
    private function log($msg, $context = [])
    {
        $logger = log_daily("CosUploadService"); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
}

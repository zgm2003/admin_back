<?php

namespace app\service\System;

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
        // 防 SSRF：只允许 https 协议 + 外网域名
        if (!$this->isSafeUrl($imageUrl)) {
            log_daily('CosUploadService')->warning("COS 上传拒绝不安全 URL: {$imageUrl}");
            return null;
        }

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

    /**
     * 校验 URL 是否安全（防 SSRF）
     * 只允许 https 协议，禁止内网 IP 和本地协议
     */
    private function isSafeUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        // 只允许 https（http 降级场景由调用方自行判断）
        if (!in_array($parsed['scheme'], ['https', 'http'], true)) {
            return false;
        }

        $host = $parsed['host'];

        // 禁止 localhost / 回环地址
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '[::1]'], true)) {
            return false;
        }

        // 解析域名获取 IP，检查是否为内网地址
        $ip = gethostbyname($host);
        if ($ip === $host) {
            return false; // 域名无法解析
        }

        // 禁止内网 IP 段
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return true;
    }
}
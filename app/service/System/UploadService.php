<?php

namespace app\service\System;

use app\dep\System\UploadSettingDep;
use app\enum\UploadConfigEnum;
use app\lib\Crypto\KeyVault;
use Qcloud\Cos\Client as CosClient;
use RuntimeException;

class UploadService
{
    private UploadSettingDep $uploadSettingDep;

    public function __construct()
    {
        $this->uploadSettingDep = new UploadSettingDep();
    }

    public function getActiveSetting(): ?array
    {
        $setting = $this->uploadSettingDep->getActive();
        if (!$setting) {
            return null;
        }

        return is_array($setting) ? $setting : $setting->toArray();
    }

    public function getActiveSettingOrFail(): array
    {
        $setting = $this->getActiveSetting();
        if (!$setting) {
            throw new RuntimeException('未配置有效的上传设置');
        }

        return $setting;
    }

    public function getActiveDriver(): string
    {
        return (string) ($this->getActiveSettingOrFail()['driver'] ?? '');
    }

    public function uploadLocalFile(string $localPath, string $folderName, ?string $filename = null, string $subDir = ''): array
    {
        if (!is_file($localPath)) {
            throw new RuntimeException('上传文件不存在');
        }

        $setting = $this->getActiveSettingOrFail();
        $storageKey = $this->buildStorageKey($folderName, $filename ?: basename($localPath), $subDir);

        return match ((string) ($setting['driver'] ?? '')) {
            UploadConfigEnum::DRIVER_COS => $this->uploadLocalFileToCos($setting, $localPath, $storageKey),
            UploadConfigEnum::DRIVER_OSS => $this->throwOssServerUploadNotImplemented(),
            default => throw new RuntimeException('不支持的上传驱动类型'),
        };
    }

    public function uploadContent(string $content, string $folderName, string $filename, string $subDir = ''): array
    {
        $setting = $this->getActiveSettingOrFail();
        $storageKey = $this->buildStorageKey($folderName, $filename, $subDir);

        return match ((string) ($setting['driver'] ?? '')) {
            UploadConfigEnum::DRIVER_COS => $this->uploadContentToCos($setting, $content, $storageKey),
            UploadConfigEnum::DRIVER_OSS => $this->throwOssServerUploadNotImplemented(),
            default => throw new RuntimeException('不支持的上传驱动类型'),
        };
    }

    public function uploadFromUrl(string $sourceUrl, string $folderName, ?string $filename = null, string $subDir = ''): array
    {
        if (!$this->isSafeUrl($sourceUrl)) {
            throw new RuntimeException('远程文件地址不安全');
        }

        $content = @file_get_contents($sourceUrl);
        if ($content === false) {
            throw new RuntimeException('下载远程文件失败');
        }

        $resolvedFilename = $filename;
        if ($resolvedFilename === null || trim($resolvedFilename) === '') {
            $path = (string) parse_url($sourceUrl, PHP_URL_PATH);
            $resolvedFilename = basename($path) ?: 'file';
        }

        return $this->uploadContent($content, $folderName, $resolvedFilename, $subDir);
    }

    private function uploadLocalFileToCos(array $setting, string $localPath, string $storageKey): array
    {
        $client = $this->buildCosClient($setting);
        $result = $client->upload(
            (string) $setting['bucket'],
            $storageKey,
            fopen($localPath, 'rb')
        );

        $url = $this->buildCosUrl($setting, $storageKey, (string) ($result['Location'] ?? ''));

        return [
            'provider' => UploadConfigEnum::DRIVER_COS,
            'driver' => UploadConfigEnum::DRIVER_COS,
            'key' => $storageKey,
            'url' => $url,
            'size' => filesize($localPath) ?: 0,
            'bucket' => (string) $setting['bucket'],
            'region' => (string) $setting['region'],
        ];
    }

    private function uploadContentToCos(array $setting, string $content, string $storageKey): array
    {
        $client = $this->buildCosClient($setting);
        $result = $client->putObject([
            'Bucket' => (string) $setting['bucket'],
            'Key' => $storageKey,
            'Body' => $content,
        ]);

        $url = $this->buildCosUrl($setting, $storageKey, (string) ($result['Location'] ?? ''));

        return [
            'provider' => UploadConfigEnum::DRIVER_COS,
            'driver' => UploadConfigEnum::DRIVER_COS,
            'key' => $storageKey,
            'url' => $url,
            'size' => strlen($content),
            'bucket' => (string) $setting['bucket'],
            'region' => (string) $setting['region'],
        ];
    }

    private function buildCosClient(array $setting): CosClient
    {
        $secretId = KeyVault::decrypt((string) ($setting['secret_id_enc'] ?? ''));
        $secretKey = KeyVault::decrypt((string) ($setting['secret_key_enc'] ?? ''));
        $region = (string) ($setting['region'] ?? '');
        $bucket = (string) ($setting['bucket'] ?? '');

        if ($secretId === '' || $secretKey === '' || $region === '' || $bucket === '') {
            throw new RuntimeException('COS 配置缺失');
        }

        return new CosClient([
            'region' => $region,
            'schema' => 'https',
            'credentials' => [
                'secretId' => $secretId,
                'secretKey' => $secretKey,
            ],
        ]);
    }

    private function buildCosUrl(array $setting, string $storageKey, string $fallbackLocation = ''): string
    {
        $bucketDomain = trim((string) ($setting['bucket_domain'] ?? ''));
        if ($bucketDomain !== '') {
            return 'https://' . $bucketDomain . '/' . $this->encodeObjectKey($storageKey);
        }

        if ($fallbackLocation !== '') {
            return str_starts_with($fallbackLocation, 'http')
                ? $fallbackLocation
                : 'https://' . $fallbackLocation;
        }

        return 'https://' . $setting['bucket'] . '.cos.' . $setting['region'] . '.myqcloud.com/' . $this->encodeObjectKey($storageKey);
    }

    private function buildStorageKey(string $folderName, string $filename, string $subDir = ''): string
    {
        $folderName = $this->normalizeFolderName($folderName);
        $subDir = $this->normalizeSubDir($subDir);
        $filename = $this->safeFileName($filename);

        if ($filename === '') {
            throw new RuntimeException('文件名不能为空');
        }

        $baseDir = $folderName;
        if ($subDir !== '') {
            $baseDir .= '/' . $subDir;
        }

        return $baseDir . '/' . $filename;
    }

    private function normalizeFolderName(string $folderName): string
    {
        $folderName = trim(str_replace('\\', '/', $folderName), '/');
        if ($folderName === '') {
            throw new RuntimeException('上传目录不能为空');
        }

        if (str_contains($folderName, '/') || str_contains($folderName, '..')) {
            throw new RuntimeException('上传目录非法');
        }

        if (!array_key_exists($folderName, UploadConfigEnum::$folderArr)) {
            throw new RuntimeException('上传目录不在白名单中');
        }

        return $folderName;
    }

    private function normalizeSubDir(string $subDir): string
    {
        $subDir = trim(str_replace('\\', '/', $subDir), '/');
        if ($subDir === '') {
            return '';
        }

        if (str_contains($subDir, '..')) {
            throw new RuntimeException('子目录非法');
        }

        return $subDir;
    }

    private function safeFileName(string $filename): string
    {
        $filename = trim($filename);
        $filename = str_replace(["\\", '/'], '_', $filename);
        $filename = preg_replace('/[^\w.\-]/u', '_', $filename) ?: '';

        return ltrim($filename, '.');
    }

    private function encodeObjectKey(string $storageKey): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $storageKey)));
    }

    private function isSafeUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        if (!in_array($parsed['scheme'], ['https', 'http'], true)) {
            return false;
        }

        $host = (string) $parsed['host'];
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '[::1]'], true)) {
            return false;
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function throwOssServerUploadNotImplemented(): array
    {
        throw new RuntimeException('当前版本暂未实现 OSS 服务端上传，请先启用 COS 驱动');
    }
}

<?php

namespace app\service\System;

use app\dep\System\TauriVersionDep;
use app\enum\UploadConfigEnum;
use RuntimeException;

/**
 * Tauri 更新清单服务
 * 负责：按平台生成 updater manifest，并发布到对象存储静态地址
 */
class TauriUpdaterService
{
    private TauriVersionDep $tauriVersionDep;
    private UploadService $uploadService;

    public function __construct()
    {
        $this->tauriVersionDep = new TauriVersionDep();
        $this->uploadService = new UploadService();
    }

    public function buildManifestPayload(string $platform): array
    {
        $platform = $this->normalizePlatform($platform);
        $latest = $this->tauriVersionDep->getLatest($platform);
        if (!$latest) {
            return [];
        }

        return [
            'version' => (string) $latest->version,
            'notes' => (string) ($latest->notes ?? ''),
            'pub_date' => date('c', strtotime((string) ($latest->updated_at ?: $latest->created_at))),
            'platforms' => [
                $platform => [
                    'url' => (string) $latest->file_url,
                    'signature' => (string) $latest->signature,
                ],
            ],
        ];
    }

    public function uploadManifest(string $platform): array
    {
        $platform = $this->normalizePlatform($platform);
        $payload = $this->buildManifestPayload($platform);
        if ($payload === []) {
            throw new RuntimeException('当前平台暂无最新版本，无法发布 update.json');
        }

        $content = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($content === false) {
            throw new RuntimeException('生成 update.json 内容失败');
        }

        return $this->uploadService->uploadContent(
            $content,
            UploadConfigEnum::FOLDER_TAURI_UPDATER,
            $this->buildManifestFilename($platform)
        );
    }

    private function buildManifestFilename(string $platform): string
    {
        return $this->normalizePlatform($platform) . '.json';
    }

    private function normalizePlatform(string $platform): string
    {
        if (!array_key_exists($platform, UploadConfigEnum::$tauriPlatformArr)) {
            throw new RuntimeException('不支持的 Tauri 平台');
        }

        return $platform;
    }
}

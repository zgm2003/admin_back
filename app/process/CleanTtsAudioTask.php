<?php

namespace app\process;

use app\dep\Ai\GoodsDep;

/**
 * 清理过期TTS音频文件
 * - 清理 public/audio/tts 下超过 7 天的 mp3 文件
 * - 清除对应商品记录的 audio_url 字段
 */
class CleanTtsAudioTask extends BaseCronTask
{
    protected string $audioDir;
    protected int $retainDays = 7;

    public function __construct()
    {
        parent::__construct();
        $this->audioDir = public_path() . '/audio/tts';
    }

    protected function getTaskName(): string
    {
        return 'clean_tts_audio';
    }

    protected function handle(): ?string
    {
        if (!is_dir($this->audioDir)) {
            return null;
        }

        $now = time();
        $expireSeconds = $this->retainDays * 86400;
        $deletedCount = 0;
        $goodsIds = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->audioDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            try {
                if ($file->isFile() && $now - $file->getMTime() > $expireSeconds) {
                    // 从文件名提取商品ID（格式：{id}_{timestamp}.mp3）
                    $basename = $file->getBasename('.mp3');
                    $parts = explode('_', $basename, 2);
                    if (!empty($parts[0]) && is_numeric($parts[0])) {
                        $goodsIds[] = (int)$parts[0];
                    }
                    @unlink($file->getRealPath());
                    $deletedCount++;
                }
                // 清理空的日期目录
                if ($file->isDir() && !(new \FilesystemIterator($file->getRealPath()))->valid()) {
                    @rmdir($file->getRealPath());
                }
            } catch (\Throwable $e) {
                // 忽略单个文件删除失败
            }
        }

        // 批量清除已删除文件对应的 audio_url
        $dbCount = 0;
        if (!empty($goodsIds)) {
            $dbCount = (new GoodsDep())->clearAudioUrl(array_unique($goodsIds));
        }

        $parts = [];
        if ($deletedCount > 0) $parts[] = "清理了 {$deletedCount} 个过期音频文件";
        if ($dbCount > 0) $parts[] = "清除了 {$dbCount} 条音频链接";

        return !empty($parts) ? implode(', ', $parts) : null;
    }
}

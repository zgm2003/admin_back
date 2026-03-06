<?php

namespace app\process;

use app\dep\System\ExportTaskDep;

/**
 * 清理过期导出任务
 * - 清理 public/export 下超过 7 天的文件
 * - 清理数据库中过期的记录
 */
class CleanExportTask extends BaseCronTask
{
    protected string $exportDir;
    protected int $retainDays = 7;

    public function __construct()
    {
        parent::__construct();
        $this->exportDir = public_path() . '/export';
    }

    protected function getTaskName(): string
    {
        return 'clean_export';
    }

    protected function handle(): ?string
    {
        $fileCount = $this->cleanOldFiles();
        $recordCount = $this->cleanExpiredRecords();
        
        $parts = [];
        if ($fileCount > 0) $parts[] = "清理了 {$fileCount} 个过期文件";
        if ($recordCount > 0) $parts[] = "清理了 {$recordCount} 条过期记录";
        
        return !empty($parts) ? implode(', ', $parts) : null;
    }

    protected function cleanOldFiles(): int
    {
        if (!is_dir($this->exportDir)) {
            return 0;
        }

        $now = time();
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->exportDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        $deletedCount = 0;
        foreach ($files as $file) {
            try {
                if ($file->isFile() && $now - $file->getMTime() > $this->retainDays * 86400) {
                    @unlink($file->getRealPath());
                    $deletedCount++;
                }
                if ($file->isDir() && !(new \FilesystemIterator($file->getRealPath()))->valid()) {
                    @rmdir($file->getRealPath());
                }
            } catch (\Throwable $e) {
                // 忽略单个文件删除失败
            }
        }
        return $deletedCount;
    }

    protected function cleanExpiredRecords(): int
    {
        $exportTaskDep = new ExportTaskDep();
        return $exportTaskDep->cleanExpired();
    }
}

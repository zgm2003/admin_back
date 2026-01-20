<?php

namespace app\process;

use app\dep\DevTools\ExportTaskDep;
use Workerman\Crontab\Crontab;

/**
 * 清理过期导出任务
 * 
 * 说明：
 * - 清理 public/export 下超过 7 天的文件
 * - 清理数据库中过期的记录
 */
class CleanExportTask
{
    protected string $exportDir;
    protected int $retainDays = 7;

    public function __construct()
    {
        $this->exportDir = public_path() . '/export';
    }

    public function onWorkerStart()
    {
        // 每天凌晨1点执行清理
        new Crontab('0 0 1 * * *', function () {
            $this->cleanOldFiles();
            $this->cleanExpiredRecords();
        });
    }

    /**
     * 清理过期的本地文件
     */
    public function cleanOldFiles()
    {
        if (!is_dir($this->exportDir)) {
            return;
        }

        $now = time();
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->exportDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        $deletedCount = 0;
        foreach ($files as $file) {
            try {
                // 删除超过保留天数的文件
                if ($file->isFile() && $now - $file->getMTime() > $this->retainDays * 86400) {
                    @unlink($file->getRealPath());
                    $deletedCount++;
                }
                // 删除空目录
                if ($file->isDir() && !(new \FilesystemIterator($file->getRealPath()))->valid()) {
                    @rmdir($file->getRealPath());
                }
            } catch (\Throwable $e) {
                // 忽略单个文件删除失败
            }
        }

        if ($deletedCount > 0) {
            echo date('Y-m-d H:i:s') . " 清理了 {$deletedCount} 个过期导出文件\n";
        }
    }

    /**
     * 清理过期的数据库记录
     */
    public function cleanExpiredRecords()
    {
        try {
            $exportTaskDep = new ExportTaskDep();
            $count = $exportTaskDep->cleanExpired();
            
            if ($count > 0) {
                echo date('Y-m-d H:i:s') . " 清理了 {$count} 条过期导出任务记录\n";
            }
        } catch (\Throwable $e) {
            echo date('Y-m-d H:i:s') . " CleanExportTask error: " . $e->getMessage() . "\n";
        }
    }
}

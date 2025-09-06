<?php

namespace app\process;

use Workerman\Crontab\Crontab;

class CleanExportTask
{
    /**
     * 配置参数
     */
    protected string $exportDir;   // 导出文件根目录
    protected int $retainDays;     // 保留天数

    public function __construct()
    {
        // 默认导出目录
        $this->exportDir = __DIR__ . '/../../public/export';
        $this->retainDays = 7; // 默认保留7天
    }

    public function onWorkerStart()
    {
        // 每天凌晨1点执行清理，可按需修改 cron 表达式
        new Crontab('0 0 1 * * *', function () {
            $this->cleanOldFiles();
        });
    }

    /**
     * 执行清理
     */
    public function cleanOldFiles()
    {
        $now = time();
        $exportRoot = $this->exportDir;

        if (!is_dir($exportRoot)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($exportRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            try {
                // 删除超过保留天数的文件
                if ($file->isFile() && $now - $file->getMTime() > $this->retainDays * 86400) {
                    @unlink($file->getRealPath());
                }

                // 删除空目录
                if ($file->isDir() && !(new \FilesystemIterator($file->getRealPath()))->valid()) {
                    @rmdir($file->getRealPath());
                }
            } catch (\Throwable $e) {
                // 可选：记录日志
                // error_log("CleanExportTask error: " . $e->getMessage());
            }
        }
    }

    /**
     * 可动态设置保留天数
     */
    public function setRetainDays(int $days)
    {
        $this->retainDays = $days;
    }

    /**
     * 可动态设置导出目录
     */
    public function setExportDir(string $dir)
    {
        $this->exportDir = $dir;
    }
}

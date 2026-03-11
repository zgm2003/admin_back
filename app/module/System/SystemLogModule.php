<?php

namespace app\module\System;

use app\module\BaseModule;
use app\service\Common\DictService;

/**
 * 系统日志模块
 * 负责：运行日志文件浏览、日志内容读取（支持关键字/级别过滤、尾部读取）
 */
class SystemLogModule extends BaseModule
{
    /**
     * 初始化（返回日志级别、尾部行数等字典）
     */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setLogLevelArr()
            ->setLogTailArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 获取日志文件列表
     * 扫描 runtime/logs 目录及一级子目录（如 redis-queue），返回文件名、大小、修改时间
     */
    public function files($request): array
    {
        $logDir = $this->getLogDir();
        $files = [];

        if (!is_dir($logDir)) {
            return self::success(['list' => []]);
        }

        // 扫描主目录下的 .log 文件
        foreach (glob("{$logDir}/*.log") as $file) {
            $files[] = $this->buildFileInfo(basename($file), $file);
        }

        // 扫描一级子目录（如 redis-queue/xxx.log）
        foreach (glob("{$logDir}/*", GLOB_ONLYDIR) as $dir) {
            $dirName = basename($dir);
            foreach (glob("{$dir}/*.log") as $file) {
                $files[] = $this->buildFileInfo("{$dirName}/" . basename($file), $file);
            }
        }

        // 按修改时间倒序，最新的排前面
        usort($files, fn($a, $b) => strcmp($b['mtime'], $a['mtime']));

        return self::success(['list' => $files]);
    }

    /**
     * 读取日志文件内容
     * 支持：尾部 N 行读取、关键字过滤、日志级别过滤
     */
    public function content($request): array
    {
        $logDir  = $this->getLogDir();
        $filename = $request->post('filename', '');
        $keyword  = $request->post('keyword', '');
        $level    = $request->post('level', '');
        $tail     = (int) $request->post('tail', 500);

        // 安全校验：防止 ../ 目录穿越和空字节注入
        self::throwIf(
            empty($filename) || str_contains($filename, '..') || str_contains($filename, "\0"),
            '文件名不合法'
        );

        $filepath = "{$logDir}/{$filename}";
        self::throwIf(!is_file($filepath), '日志文件不存在');

        // 限制最大读取行数，防止内存溢出
        $tail = min($tail, 2000);

        $lines = $this->tailFile($filepath, $tail);

        // 关键字过滤（不区分大小写）
        if ($keyword !== '') {
            $lines = array_values(array_filter($lines, fn($line) => stripos($line, $keyword) !== false));
        }

        // 日志级别过滤（匹配 monolog 格式：channel.LEVEL:）
        if ($level !== '') {
            $levelUpper = strtoupper($level);
            $lines = array_values(array_filter($lines, fn($line) => str_contains($line, ".{$levelUpper}:")));
        }

        return self::success([
            'lines'    => $lines,
            'total'    => count($lines),
            'filename' => $filename,
        ]);
    }

    // ==================== 私有方法 ====================

    /**
     * 获取日志根目录路径
     */
    private function getLogDir(): string
    {
        return base_path() . '/runtime/logs';
    }

    /**
     * 构建单个文件信息数组
     */
    private function buildFileInfo(string $name, string $fullPath): array
    {
        return [
            'name'       => $name,
            'size'       => filesize($fullPath),
            'size_human' => $this->formatSize(filesize($fullPath)),
            'mtime'      => date('Y-m-d H:i:s', filemtime($fullPath)),
        ];
    }

    /**
     * 从文件末尾高效读取 N 行
     * 采用反向分块读取策略，不会一次性加载整个文件到内存
     */
    private function tailFile(string $filepath, int $lines): array
    {
        $fp = fopen($filepath, 'r');
        if (!$fp) return [];

        $result = [];
        $buffer = '';
        $fileSize = filesize($filepath);

        if ($fileSize === 0) {
            fclose($fp);
            return [];
        }

        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);

        // 每次读取 4KB 块，从文件尾部向前推进
        while ($pos > 0 && count($result) < $lines) {
            $readSize = min(4096, $pos);
            $pos -= $readSize;
            fseek($fp, $pos);
            $buffer = fread($fp, $readSize) . $buffer;

            $parts = explode("\n", $buffer);
            $buffer = array_shift($parts); // 第一段可能不完整，留到下一轮

            foreach (array_reverse($parts) as $line) {
                if (trim($line) !== '') {
                    array_unshift($result, $line);
                }
                if (count($result) >= $lines) break;
            }
        }

        // 处理最后剩余的不完整行
        if (count($result) < $lines && trim($buffer) !== '') {
            array_unshift($result, $buffer);
        }

        fclose($fp);

        return array_slice($result, -$lines);
    }

    /**
     * 格式化文件大小为人类可读格式
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

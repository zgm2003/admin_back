<?php

namespace app\module\System;

use app\module\BaseModule;
use app\service\DictService;

class SystemLogModule extends BaseModule
{
    private string $logDir;
    protected DictService $dictService;

    public function __construct()
    {
        $this->logDir = base_path() . '/runtime/logs';
        $this->dictService = $this->svc(DictService::class);
    }

    /**
     * 初始化字典数据
     */
    public function init($request): array
    {
        $data['dict'] = $this->dictService
            ->setLogLevelArr()
            ->setLogTailArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 获取日志文件列表
     */
    public function files($request): array
    {
        $files = [];

        if (!is_dir($this->logDir)) {
            return self::success(['list' => []]);
        }

        // 扫描主目录下的 .log 文件
        foreach (glob($this->logDir . '/*.log') as $file) {
            $filename = basename($file);
            $files[] = [
                'name'       => $filename,
                'size'       => filesize($file),
                'size_human' => $this->formatSize(filesize($file)),
                'mtime'      => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        // 扫描子目录（如 redis-queue）
        foreach (glob($this->logDir . '/*', GLOB_ONLYDIR) as $dir) {
            $dirName = basename($dir);
            foreach (glob($dir . '/*.log') as $file) {
                $filename = $dirName . '/' . basename($file);
                $files[] = [
                    'name'       => $filename,
                    'size'       => filesize($file),
                    'size_human' => $this->formatSize(filesize($file)),
                    'mtime'      => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
        }

        // 按修改时间倒序
        usort($files, fn($a, $b) => strcmp($b['mtime'], $a['mtime']));

        return self::success(['list' => $files]);
    }

    /**
     * 读取日志文件内容
     */
    public function content($request): array
    {
        $filename = $request->post('filename', '');
        $keyword  = $request->post('keyword', '');
        $level    = $request->post('level', '');
        $tail     = (int) $request->post('tail', 500); // 默认读取最后500行

        // 安全校验：防止目录穿越
        self::throwIf(
            empty($filename) || str_contains($filename, '..') || str_contains($filename, "\0"),
            '文件名不合法'
        );

        $filepath = $this->logDir . '/' . $filename;

        self::throwIf(!is_file($filepath), '日志文件不存在');

        // 限制最大读取行数
        $tail = min($tail, 2000);

        // 从文件末尾读取指定行数
        $lines = $this->tailFile($filepath, $tail);

        // 按关键字过滤
        if ($keyword !== '') {
            $lines = array_values(array_filter($lines, fn($line) => stripos($line, $keyword) !== false));
        }

        // 按日志级别过滤
        if ($level !== '') {
            $levelUpper = strtoupper($level);
            $lines = array_values(array_filter($lines, fn($line) => str_contains($line, '.' . $levelUpper . ':')));
        }

        return self::success([
            'lines'    => $lines,
            'total'    => count($lines),
            'filename' => $filename,
        ]);
    }

    /**
     * 从文件末尾读取 N 行（高效实现，不会一次性读入整个文件）
     */
    private function tailFile(string $filepath, int $lines): array
    {
        $fp = fopen($filepath, 'r');
        if (!$fp) return [];

        $result = [];
        $buffer = '';
        $pos = -1;
        $fileSize = filesize($filepath);

        if ($fileSize === 0) {
            fclose($fp);
            return [];
        }

        // 从文件末尾向前读取
        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);

        while ($pos > 0 && count($result) < $lines) {
            $readSize = min(4096, $pos);
            $pos -= $readSize;
            fseek($fp, $pos);
            $buffer = fread($fp, $readSize) . $buffer;

            // 按行分割
            $parts = explode("\n", $buffer);
            $buffer = array_shift($parts); // 第一段可能不完整，留到下次

            // 将完整行加入结果
            foreach (array_reverse($parts) as $line) {
                if (trim($line) !== '') {
                    array_unshift($result, $line);
                }
                if (count($result) >= $lines) break;
            }
        }

        // 处理剩余的 buffer
        if (count($result) < $lines && trim($buffer) !== '') {
            array_unshift($result, $buffer);
        }

        fclose($fp);

        // 只返回最后 N 行
        return array_slice($result, -$lines);
    }

    /**
     * 格式化文件大小
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

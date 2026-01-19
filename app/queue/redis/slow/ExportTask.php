<?php

namespace app\queue\redis\slow;

use app\service\ExportService;
use GatewayWorker\Lib\Gateway;
use Webman\RedisQueue\Consumer;

/**
 * 异步导出任务
 */
class ExportTask implements Consumer
{
    public $queue = 'export-task';
    public $connection = 'default';

    public function consume($data)
    {
        $userId = $data['user_id'];
        $headers = $data['headers'];
        $rows = $data['data'];
        $prefix = $data['prefix'] ?? 'export';
        $title = $data['title'] ?? '数据导出';

        $this->log('开始导出', ['user_id' => $userId, 'rows' => count($rows)]);

        // 执行导出
        $exportService = new ExportService();
        $url = $exportService->export($headers, $rows, $prefix);

        // WebSocket 推送导出完成通知
        Gateway::$registerAddress = '127.0.0.1:1236';
        
        if (Gateway::isUidOnline($userId)) {
            Gateway::sendToUid($userId, json_encode([
                'type' => 'export_complete',
                'data' => [
                    'title' => $title,
                    'url' => $url,
                    'message' => '导出完成，点击下载'
                ]
            ]));
        }

        $this->log('导出成功', ['user_id' => $userId, 'url' => $url]);
    }

    public function onConsumeFailure(\Throwable $e, $package)
    {
        $data = $package['data'] ?? [];
        $userId = $data['user_id'] ?? null;
        $title = $data['title'] ?? '数据导出';

        // 推送失败通知
        if ($userId) {
            Gateway::$registerAddress = '127.0.0.1:1236';
            if (Gateway::isUidOnline($userId)) {
                Gateway::sendToUid($userId, json_encode([
                    'type' => 'notification',
                    'data' => [
                        'title' => $title,
                        'content' => '导出失败: ' . $e->getMessage(),
                        'type' => 'error'
                    ]
                ]));
            }
        }

        $this->log('队列消费失败', ['error' => $e->getMessage()]);
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue);
        $logger->info($msg, $context);
    }
}

<?php

namespace app\queue\redis\slow;

use app\dep\DevTools\ExportTaskDep;
use app\service\ExportService;
use GatewayWorker\Lib\Gateway;
use Webman\RedisQueue\Consumer;

/**
 * 异步导出任务
 */
class ExportTask implements Consumer
{
    public $queue = 'export_task';
    public $connection = 'default';

    public function consume($data)
    {
        $taskId = $data['task_id'];
        $userId = $data['user_id'];
        $headers = $data['headers'];
        $rows = $data['data'];
        $prefix = $data['prefix'] ?? 'export';
        $title = $data['title'] ?? '数据导出';

        $this->log('开始导出', ['task_id' => $taskId, 'rows' => count($rows)]);
        $dep = new ExportTaskDep();

        try {
            $result = (new ExportService())->export($headers, $rows, $prefix);
            $dep->updateSuccess($taskId, $result);
            $this->notifyUser($userId, [
                'type' => 'export_complete',
                'data' => [
                    'task_id' => $taskId,
                    'title' => $title,
                    'url' => $result['url'],
                    'message' => '导出完成，点击下载'
                ]
            ]);
            $this->log('导出成功', ['task_id' => $taskId, 'url' => $result['url']]);
        } catch (\Throwable $e) {
            $dep->updateFailed($taskId, $e->getMessage());
            $this->log('导出失败', ['task_id' => $taskId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function onConsumeFailure(\Throwable $e, $package)
    {
        $data = $package['data'] ?? [];
        $taskId = $data['task_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $title = $data['title'] ?? '数据导出';

        if ($taskId) {
            (new ExportTaskDep())->updateFailed($taskId, '重试次数耗尽: ' . $e->getMessage());
        }
        if ($userId) {
            $this->notifyUser($userId, [
                'type' => 'export_failed',
                'data' => [
                    'task_id' => $taskId,
                    'title' => $title,
                    'message' => '导出失败: ' . $e->getMessage()
                ]
            ]);
        }
        $this->log('队列消费最终失败', ['task_id' => $taskId, 'error' => $e->getMessage()]);
    }

    private function notifyUser(int $userId, array $message): void
    {
        try {
            Gateway::$registerAddress = '127.0.0.1:1236';
            if (Gateway::isUidOnline($userId)) {
                Gateway::sendToUid($userId, json_encode($message));
            }
        } catch (\Throwable $e) {
            $this->log('WebSocket推送失败', ['error' => $e->getMessage()]);
        }
    }

    private function log($msg, $context = [])
    {
        log_daily('queue_' . $this->queue)->info($msg, $context);
    }
}

<?php

namespace app\queue\redis\slow;

use app\dep\DevTools\ExportTaskDep;
use app\service\ExportService;
use app\service\NotificationService;
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
            
            // 发送通知：导出完成
            NotificationService::sendUrgent(
                $userId,
                $title . ' - 导出完成',
                '点击查看并下载导出文件',
                [
                    'type' => NotificationService::TYPE_SUCCESS,
                    'link' => '/devTools/exportTask'
                ]
            );
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
            // 发送通知：导出失败
            NotificationService::sendUrgent(
                $userId,
                $title . ' - 导出失败',
                '导出任务失败，请重试',
                [
                    'type' => NotificationService::TYPE_ERROR,
                    'link' => '/devTools/exportTask'
                ]
            );
        }
        $this->log('队列消费最终失败', ['task_id' => $taskId, 'error' => $e->getMessage()]);
    }

    private function log($msg, $context = [])
    {
        log_daily('queue_' . $this->queue)->info($msg, $context);
    }
}

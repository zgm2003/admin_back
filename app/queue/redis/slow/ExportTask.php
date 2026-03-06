<?php

namespace app\queue\redis\slow;

use app\dep\System\ExportTaskDep;
use app\service\ExportService;
use app\enum\NotificationEnum;
use app\service\System\NotificationService;
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
        $platform = $data['platform'] ?? 'admin'; // 推送到发起导出的平台
        $title = $data['title'] ?? '数据导出';

        $this->log('开始导出', ['task_id' => $taskId, 'rows' => count($data['data'])]);

        $result = (new ExportService())->export($data['headers'], $data['data'], $data['prefix'] ?? 'export');
        (new ExportTaskDep())->updateSuccess($taskId, $result);
        
        NotificationService::sendUrgent($userId, $title . ' - 导出完成', '点击查看导出文件', [
            'type' => NotificationEnum::TYPE_SUCCESS,
            'link' => '/system/exportTask?status=2',
            'platform' => $platform, // 只推送到发起导出的平台
        ]);
        $this->log('导出成功', ['task_id' => $taskId, 'url' => $result['url']]);
    }

    public function onConsumeFailure(\Throwable $e, $package)
    {
        $data = $package['data'] ?? [];
        $taskId = $data['task_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $platform = $data['platform'] ?? 'admin';
        $title = $data['title'] ?? '数据导出';

        if ($taskId) {
            (new ExportTaskDep())->updateFailed($taskId, '重试次数耗尽: ' . $e->getMessage());
        }
        if ($userId) {
            NotificationService::sendUrgent(
                $userId,
                $title . ' - 导出失败',
                '导出任务失败，请重试',
                [
                    'type' => NotificationEnum::TYPE_ERROR,
                    'link' => '/system/exportTask?status=3',
                    'platform' => $platform,
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

<?php

namespace app\queue\redis\slow;

use app\dep\System\NotificationTaskDep;
use app\dep\User\UsersDep;
use app\enum\NotificationEnum;
use app\service\System\NotificationService;
use Webman\RedisQueue\Consumer;

/**
 * 通知任务消费者
 */
class NotificationTask implements Consumer
{
    public $queue = 'notification_task';
    public $connection = 'default';

    /** 每批发送数量 */
    private const BATCH_SIZE = 100;

    public function consume($data)
    {
        $taskId = $data['task_id'];
        $notificationTaskDep = new NotificationTaskDep();
        $usersDep = new UsersDep();

        // 获取任务详情
        $task = $notificationTaskDep->get($taskId);
        if (!$task) {
            $this->log('任务不存在', ['task_id' => $taskId]);
            return;
        }

        // 更新状态为发送中
        $notificationTaskDep->updateStatus($taskId, NotificationEnum::STATUS_SENDING);

        // 获取目标用户ID列表
        $userIds = $this->getTargetUserIds($task, $usersDep);
        $totalCount = count($userIds);

        // 更新目标用户数
        $notificationTaskDep->updateStatus($taskId, NotificationEnum::STATUS_SENDING, $totalCount);

        $this->log('开始发送通知', ['task_id' => $taskId, 'total' => $totalCount]);

        // 分批发送
        $sentCount = 0;
        $chunks = array_chunk($userIds, self::BATCH_SIZE);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $userId) {
                NotificationService::send(
                    $userId,
                    $task->title,
                    $task->content ?? '',
                    [
                        'type' => NotificationEnum::getTypeStr($task->type),
                        'level' => NotificationEnum::getLevelStr($task->level),
                        'link' => $task->link ?? '',
                    ]
                );
                $sentCount++;
            }
            // 更新进度
            $notificationTaskDep->update($taskId, ['sent_count' => $sentCount]);
        }

        // 更新状态为已完成
        $notificationTaskDep->updateStatus($taskId, NotificationEnum::STATUS_SUCCESS);

        $this->log('通知发送完成', ['task_id' => $taskId, 'sent' => $sentCount]);
    }

    public function onConsumeFailure(\Throwable $e, $package)
    {
        $data = $package['data'] ?? [];
        $taskId = $data['task_id'] ?? null;

        if ($taskId) {
            (new NotificationTaskDep())->updateStatus(
                $taskId,
                NotificationEnum::STATUS_FAILED,
                null,
                '重试次数耗尽: ' . $e->getMessage()
            );
        }

        $this->log('队列消费最终失败', ['task_id' => $taskId, 'error' => $e->getMessage()]);
    }

    /**
     * 获取目标用户ID列表
     */
    private function getTargetUserIds($task, UsersDep $usersDep): array
    {
        return match ((int)$task->target_type) {
            NotificationEnum::TARGET_ALL => $usersDep->all()->pluck('id')->toArray(),
            NotificationEnum::TARGET_USERS => json_decode($task->target_ids, true) ?? [],
            NotificationEnum::TARGET_ROLES => $usersDep->getIdsByRoleIds(json_decode($task->target_ids, true) ?? [])->toArray(),
            default => [],
        };
    }

    private function log($msg, $context = [])
    {
        log_daily('queue_' . $this->queue)->info($msg, $context);
    }
}

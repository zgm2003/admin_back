<?php

namespace app\process;

use app\dep\System\NotificationTaskDep;
use app\enum\NotificationEnum;
use Webman\RedisQueue\Client as RedisQueue;

/**
 * 通知任务调度器
 * 定时检查待发送的通知任务并入队
 */
class NotificationTaskScheduler extends BaseCronTask
{
    protected function getTaskName(): string
    {
        return 'notification_task_scheduler';
    }

    protected function handle(): ?string
    {
        $notificationTaskDep = new NotificationTaskDep();
        $tasks = $notificationTaskDep->getPendingTasks();

        if (empty($tasks)) {
            return '无待发送任务';
        }

        $count = 0;
        foreach ($tasks as $task) {
            // 先更新状态为「发送中」，防止重复入队
            $notificationTaskDep->updateStatus($task['id'], NotificationEnum::STATUS_SENDING);
            RedisQueue::send('notification_task', ['task_id' => $task['id']]);
            $count++;
        }

        return "已将 {$count} 个任务加入队列";
    }
}

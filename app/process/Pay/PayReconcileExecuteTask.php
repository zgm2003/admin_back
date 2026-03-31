<?php

namespace app\process\Pay;

use app\process\BaseCronTask;
use app\service\Pay\PayReconcileService;
use support\Log;

class PayReconcileExecuteTask extends BaseCronTask
{
    protected function getTaskName(): string
    {
        return 'pay_reconcile_execute';
    }

    protected function handle(): ?string
    {
        $tasks = (new PayReconcileService())->getPendingTasks(20);
        $processed = 0;

        foreach ($tasks as $task) {
            try {
                (new PayReconcileService())->execute((int) $task['id']);
                $processed++;
            } catch (\Throwable $e) {
                Log::error("[PayReconcileExecute] 执行失败 task_id={$task['id']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed > 0 ? "执行了 {$processed} 条对账任务" : null;
    }
}

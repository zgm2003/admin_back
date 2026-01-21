<?php

namespace app\process;

use app\dep\DevTools\CronTaskDep;
use app\dep\DevTools\CronTaskLogDep;
use Workerman\Crontab\Crontab;

/**
 * 定时任务基类
 * 提供任务启用检查和日志记录功能
 */
abstract class BaseCronTask
{
    protected CronTaskDep $cronTaskDep;
    protected CronTaskLogDep $cronTaskLogDep;

    public function __construct()
    {
        $this->cronTaskDep = new CronTaskDep();
        $this->cronTaskLogDep = new CronTaskLogDep();
    }

    /**
     * 获取任务标识（子类必须实现）
     */
    abstract protected function getTaskName(): string;

    /**
     * 执行任务逻辑（子类必须实现）
     * @return string|null 执行结果描述
     */
    abstract protected function handle(): ?string;

    /**
     * Worker 启动时注册定时任务
     */
    public function onWorkerStart(): void
    {
        $task = $this->cronTaskDep->findByName($this->getTaskName());
        if (!$task || empty($task->cron)) {
            return;
        }
        new Crontab($task->cron, fn() => $this->runWithLog());
    }

    /**
     * 执行任务（带日志记录）
     */
    protected function runWithLog(): void
    {
        $taskName = $this->getTaskName();
        
        // 检查任务是否启用
        $task = $this->cronTaskDep->findByName($taskName);
        if (!$task || $task->status != 1) {
            return;
        }

        // 记录开始
        $logId = $this->cronTaskLogDep->logStart($task->id, $taskName);
        
        try {
            $result = $this->handle();
            // 记录成功
            $this->cronTaskLogDep->logEnd($logId, true, $result);
        } catch (\Throwable $e) {
            // 记录失败
            $this->cronTaskLogDep->logEnd($logId, false, null, $e->getMessage());
        }
    }
}

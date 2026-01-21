<?php

namespace app\process;

use app\dep\Ai\AiRunsDep;
use Workerman\Crontab\Crontab;

/**
 * AI Run 超时检测定时任务
 * 将长时间处于 running 状态的 Run 标记为超时失败
 */
class AiRunTimeoutTask extends BaseCronTask
{
    protected int $timeoutMinutes = 10; // 超时时间（分钟）

    protected function getTaskName(): string
    {
        return 'ai_run_timeout';
    }

    public function onWorkerStart(): void
    {
        // 每分钟检查一次超时的 Run
        new Crontab('0 * * * * *', fn() => $this->runWithLog());
    }

    protected function handle(): ?string
    {
        $count = (new AiRunsDep())->markTimeoutAsFailed($this->timeoutMinutes);
        return $count > 0 ? "处理了 {$count} 条超时记录" : null;
    }
}

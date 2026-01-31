<?php

namespace app\process;

use app\dep\Ai\AiRunsDep;

/**
 * AI Run 超时检测定时任务
 * 将长时间处于 running 状态的 Run 标记为超时失败
 */
class AiRunTimeoutTask extends BaseCronTask
{
    protected int $timeoutMinutes = 10; // 超时时间（分钟）
    protected string $errorMsg = '执行超时'; // 错误文案

    protected function getTaskName(): string
    {
        return 'ai_run_timeout';
    }

    protected function handle(): ?string
    {
        // 业务策略：超时时间判定、错误文案均在 Process 层
        $threshold = date('Y-m-d H:i:s', strtotime("-{$this->timeoutMinutes} minutes"));
        $count = (new AiRunsDep())->batchMarkFailed($threshold, $this->errorMsg);
        return $count > 0 ? "处理了 {$count} 条超时记录" : null;
    }
}

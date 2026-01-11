<?php

namespace app\process;

use app\dep\Ai\AiRunsDep;
use Workerman\Crontab\Crontab;

/**
 * AI Run 超时检测定时任务
 * 将长时间处于 running 状态的 Run 标记为超时失败
 */
class AiRunTimeoutTask
{
    // 超时时间（秒），默认 10 分钟
    protected int $timeoutSeconds = 600;

    // 单次处理数量上限
    protected int $batchSize = 100;

    public function onWorkerStart(): void
    {
        // 每分钟检查一次超时的 Run
        new Crontab('0 * * * * *', function () {
            $this->checkTimeoutRuns();
        });
    }

    /**
     * 检查并处理超时的 Run
     */
    public function checkTimeoutRuns(): void
    {
        try {
            $runsDep = new AiRunsDep();

            // 查询超时的 running 状态 Run
            $timeoutAt = date('Y-m-d H:i:s', time() - $this->timeoutSeconds);
            $timeoutRuns = $runsDep->getTimeoutRuns($timeoutAt, $this->batchSize);

            if ($timeoutRuns->isEmpty()) {
                return;
            }

            foreach ($timeoutRuns as $run) {
                try {
                    $runsDep->markFailed($run->id, '执行超时（超过 ' . ($this->timeoutSeconds / 60) . ' 分钟）');
                } catch (\Throwable $e) {
                    // 单条失败不影响其他处理
                }
            }
        } catch (\Throwable $e) {
            // 静默处理，避免刷日志
        }
    }

    /**
     * 设置超时时间（秒）
     */
    public function setTimeoutSeconds(int $seconds): void
    {
        $this->timeoutSeconds = $seconds;
    }
}

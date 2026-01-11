<?php

namespace app\process;

use app\dep\Ai\AiRunsDep;
use support\Log;
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

        Log::info('AiRunTimeoutTask started, timeout: ' . $this->timeoutSeconds . 's');
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

            $count = 0;
            foreach ($timeoutRuns as $run) {
                try {
                    $runsDep->markFailed($run->id, '执行超时（超过 ' . ($this->timeoutSeconds / 60) . ' 分钟）');
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning('AiRunTimeoutTask: 标记超时失败', [
                        'run_id' => $run->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($count > 0) {
                Log::info('AiRunTimeoutTask: 已处理 ' . $count . ' 个超时 Run');
            }
        } catch (\Throwable $e) {
            Log::error('AiRunTimeoutTask error: ' . $e->getMessage());
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

<?php

namespace app\queue\redis\slow;

use app\dep\Ai\AiRunsDep;
use app\service\Ai\AiChatRunExecutor;
use app\service\Ai\AiRunEventPublisher;
use Webman\RedisQueue\Consumer;

class AiRunExecute implements Consumer
{
    public $queue = 'ai_run_execute';
    public $connection = 'default';

    public function consume($data): void
    {
        if (empty($data['run_id'])) {
            log_daily('queue_ai_run_execute')->warning('ai_run_execute missing run_id', ['data' => $data]);
            return;
        }

        (new AiChatRunExecutor())->execute((int)$data['run_id']);
    }

    public function onConsumeFailure(\Throwable $e, $package): void
    {
        $data = is_array($package) ? ($package['data'] ?? []) : [];
        $runId = (int)($data['run_id'] ?? 0);
        if ($runId > 0) {
            try {
                (new AiRunEventPublisher())->publishError($runId, 'AI 队列最终失败: ' . $e->getMessage());
            } catch (\Throwable) {
                // Redis 本身故障时，失败处理不能再把 consumer 打爆。
            }
            (new AiRunsDep())->markFailed($runId, $e->getMessage());
        }

        log_daily('queue_ai_run_execute')->error('ai_run_execute consume failure', [
            'run_id' => $runId,
            'error' => $e->getMessage(),
        ]);
    }
}

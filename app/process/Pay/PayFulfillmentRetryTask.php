<?php

namespace app\process\Pay;

use app\dep\Pay\OrderFulfillmentDep;
use app\enum\PayEnum;
use app\process\BaseCronTask;
use support\Log;
use Webman\RedisQueue\Client as RedisQueue;

/**
 * 履约失败重试定时任务
 * 每2分钟扫描待重试履约记录，重新投递队列
 */
class PayFulfillmentRetryTask extends BaseCronTask
{
    protected function getTaskName(): string
    {
        return 'pay_fulfillment_retry';
    }

    protected function handle(): ?string
    {
        $now = date('Y-m-d H:i:s');
        $tasks = (new OrderFulfillmentDep())->getRetryTasks($now, 50);

        $retried = 0;
        foreach ($tasks as $task) {
            try {
                // 重试次数已耗尽，跳过
                if ($task['retry_count'] >= PayEnum::FULFILL_MAX_RETRY) {
                    continue;
                }

                // 重新投递队列
                RedisQueue::connection('default')->send('pay_order_fulfillment', [
                    'fulfill_id' => $task['id'],
                    'order_id'   => $task['order_id'],
                ]);

                Log::info('[PayFulfillmentRetry] 重新投递履约', [
                    'fulfill_no'  => $task['fulfill_no'],
                    'retry_count' => $task['retry_count'],
                ]);
                $retried++;
            } catch (\Throwable $e) {
                Log::error("[PayFulfillmentRetry] 投递失败 fulfill_no={$task['fulfill_no']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $retried > 0 ? "重试了 {$retried} 条履约记录" : null;
    }
}

<?php

namespace app\queue\redis\slow;

use app\dep\Pay\OrderFulfillmentDep;
use app\dep\Pay\OrderDep;
use app\enum\PayEnum;
use app\service\Pay\PayDomainService;
use Webman\RedisQueue\Consumer;

class PayOrderFulfillment implements Consumer
{
    public $queue = 'pay_order_fulfillment';
    public $connection = 'default';

    public function consume($data): void
    {
        $fulfillId = $data['fulfill_id'] ?? 0;
        $this->log("开始处理履约 fulfill_id={$fulfillId}");

        $dep = new OrderFulfillmentDep();
        $fulfill = $dep->findOrFail($fulfillId);

        // 幂等：已成功则跳过
        if ($fulfill->status === PayEnum::FULFILL_SUCCESS) {
            $this->log("履约已成功，跳过 fulfill_id={$fulfillId}");
            return;
        }

        // 标记执行中
        $dep->update($fulfillId, ['status' => PayEnum::FULFILL_RUNNING]);

        try {
            $payDomainService = new PayDomainService();
            match ((int) $fulfill->action_type) {
                PayEnum::FULFILL_ACTION_RECHARGE => $payDomainService->fulfillRecharge($fulfill->toArray()),
                PayEnum::FULFILL_ACTION_CONSUME  => $payDomainService->fulfillConsume($fulfill->toArray()),
                PayEnum::FULFILL_ACTION_GOODS    => $payDomainService->fulfillGoods($fulfill->toArray()),
                default => throw new \RuntimeException("未知动作类型: {$fulfill->action_type}"),
            };

            $this->log("履约成功 fulfill_id={$fulfillId}");
        } catch (\Throwable $e) {
            $nextRetryAt = null;
            if ($fulfill->retry_count + 1 < PayEnum::FULFILL_MAX_RETRY) {
                $retryCount = $fulfill->retry_count + 1;
                $nextRetryAt = date('Y-m-d H:i:s', time() + PayEnum::FULFILL_RETRY_BASE * (2 ** $fulfill->retry_count));
                $dep->update($fulfillId, [
                    'status'        => PayEnum::FULFILL_FAILED,
                    'retry_count'   => $retryCount,
                    'last_error'    => mb_substr($e->getMessage(), 0, 500),
                    'next_retry_at' => $nextRetryAt,
                ]);
                $this->log("履约失败（将重试） fulfill_id={$fulfillId} error={$e->getMessage()}");
            } else {
                $dep->update($fulfillId, [
                    'status'     => PayEnum::FULFILL_MANUAL,
                    'last_error' => '队列重试耗尽: ' . mb_substr($e->getMessage(), 0, 400),
                ]);
                (new OrderDep())->update($data['order_id'] ?? 0, [
                    'biz_status' => PayEnum::BIZ_STATUS_MANUAL,
                ]);
                $this->log("履约最终失败，转人工 fulfill_id={$fulfillId} error={$e->getMessage()}");
            }

            // 失败由 order_fulfillments.retry_count/next_retry_at 驱动，
            // 不再叠加 redis-queue 的即时自动重试，避免双重重试。
            return;
        }
    }

    private function log(string $msg, array $context = []): void
    {
        log_daily('queue_pay_order_fulfillment')->info($msg, $context);
    }
}

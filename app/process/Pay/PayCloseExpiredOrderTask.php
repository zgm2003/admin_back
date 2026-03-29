<?php

namespace app\process\Pay;

use app\dep\Pay\OrderDep;
use app\enum\PayEnum;
use app\module\Pay\PayModule;
use app\process\BaseCronTask;
use support\Log;

/**
 * 支付超时关单定时任务
 * 每分钟扫描过期待支付订单，先查第三方再关单
 */
class PayCloseExpiredOrderTask extends BaseCronTask
{
    protected function getTaskName(): string
    {
        return 'pay_close_expired_order';
    }

    protected function handle(): ?string
    {
        $expireTime = date('Y-m-d H:i:s', time() - PayEnum::ORDER_EXPIRE_SECONDS);
        $orders = (new OrderDep())->getExpiredPending($expireTime, 50);

        $processed = 0;
        foreach ($orders as $order) {
            try {
                if ($this->processOrder($order)) {
                    $processed++;
                }
            } catch (\Throwable $e) {
                Log::error("[PayCloseExpiredOrder] 处理失败 order_no={$order['order_no']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed > 0 ? "处理了 {$processed} 笔过期订单" : null;
    }

    private function processOrder(array $order): bool
    {
        $result = (new PayModule())->syncPaidOrCloseOrder(
            (string) $order['order_no'],
            '支付超时自动关闭',
            'cron_repair'
        );

        return in_array($result, ['paid', 'closed'], true);
    }
}

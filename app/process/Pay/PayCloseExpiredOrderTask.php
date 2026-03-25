<?php

namespace app\process\Pay;

use app\dep\Pay\OrderDep;
use app\dep\Pay\PayTransactionDep;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use app\module\Pay\PayModule;
use app\module\Pay\PayNotifyModule;
use app\process\BaseCronTask;
use RuntimeException;
use support\Log;
use Yansongda\Supports\Collection;

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

        $closed = 0;
        foreach ($orders as $order) {
            try {
                $this->processOrder($order);
                $closed++;
            } catch (\Throwable $e) {
                Log::error("[PayCloseExpiredOrder] 处理失败 order_no={$order['order_no']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $closed > 0 ? "关闭了 {$closed} 笔过期订单" : null;
    }

    private function processOrder(array $order): void
    {
        $txn = (new PayTransactionDep())->findLastActive((int) $order['id']);
        if (!$txn) {
            Log::warning("[PayCloseExpiredOrder] 未找到有效支付流水，直接关闭 order_no={$order['order_no']}");
            (new PayModule())->closeWithCheck($order['order_no'], '支付超时自动关闭');
            return;
        }

        $channelId = (int) $txn->channel_id;
        $channel = (int) $txn->channel;
        $transactionNo = (string) $txn->transaction_no;
        $tradeNo = (string) ($txn->trade_no ?? '');

        // 调用第三方查单
        try {
            $queryResult = $this->queryThirdPartyOrder($channel, $channelId, $transactionNo, $tradeNo);
        } catch (RuntimeException $e) {
            Log::warning("[PayCloseExpiredOrder] 第三方查单异常 order_no={$order['order_no']}", [
                'error' => $e->getMessage(),
            ]);
            return; // 第三方查询超时/异常，跳过本次
        }

        // 1. 第三方确认已支付 → 补单
        if ($this->isThirdPartyPaid($queryResult)) {
            $tradeNo = $this->extractTradeNo($queryResult);
            Log::info("[PayCloseExpiredOrder] 第三方已支付，补单 order_no={$order['order_no']}", [
                'trade_no' => $tradeNo,
            ]);

            $notifyModule = new PayNotifyModule();
            $notifyModule->handlePaySuccess($transactionNo, $tradeNo, $channel, [
                'out_trade_no' => $transactionNo,
                'trade_no'     => $tradeNo,
                'paid_time'    => date('Y-m-d H:i:s'),
                'source'       => 'cron_repair',
            ]);
            return;
        }

        // 2. 第三方确认未支付/已关闭 → 本地关闭订单
        (new PayModule())->closeWithCheck($order['order_no'], '支付超时自动关闭');
    }

    /**
     * 调用第三方查单
     */
    private function queryThirdPartyOrder(int $channel, int $channelId, string $orderNo, string $transactionNo): mixed
    {
        if ($channel === PayEnum::CHANNEL_WECHAT) {
            return $this->queryWechatOrder($channelId, $orderNo, $transactionNo);
        }

        if ($channel === PayEnum::CHANNEL_ALIPAY) {
            return $this->queryAlipayOrder($channelId, $orderNo, $transactionNo);
        }

        throw new RuntimeException('不支持的支付渠道');
    }

    /**
     * 微信查单
     */
    private function queryWechatOrder(int $channelId, string $orderNo, string $transactionNo): mixed
    {
        $sdk = new PaySdk();
        $sdk->initWechat($channelId);

        $params = [
            'out_trade_no' => $orderNo,
        ];

        if (!empty($transactionNo)) {
            $params['transaction_id'] = $transactionNo;
        }

        return \Yansongda\Pay\Pay::wechat()->query($params);
    }

    /**
     * 支付宝查单
     */
    private function queryAlipayOrder(int $channelId, string $orderNo, string $transactionNo): mixed
    {
        $sdk = new PaySdk();
        $sdk->initAlipay($channelId);

        $params = [
            'out_trade_no' => $orderNo,
        ];

        if (!empty($transactionNo)) {
            $params['trade_no'] = $transactionNo;
        }

        return \Yansongda\Pay\Pay::alipay()->query($params);
    }

    /**
     * 判断第三方是否已支付
     */
    private function isThirdPartyPaid(mixed $result): bool
    {
        if ($result instanceof Collection) {
            $result = $result->toArray();
        }

        if (!is_array($result)) {
            return false;
        }

        // 微信支付成功标识
        if (isset($result['trade_state']) && $result['trade_state'] === 'SUCCESS') {
            return true;
        }

        // 支付宝支付成功标识
        if (isset($result['trade_status']) && in_array($result['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return true;
        }

        return false;
    }

    /**
     * 从第三方返回中提取交易号
     */
    private function extractTradeNo(mixed $result): string
    {
        if ($result instanceof Collection) {
            $result = $result->toArray();
        }

        if (!is_array($result)) {
            return '';
        }

        return $result['transaction_id'] ?? $result['trade_no'] ?? '';
    }
}

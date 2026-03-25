<?php

namespace app\process\Pay;

use app\dep\Pay\PayTransactionDep;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use app\module\Pay\PayNotifyModule;
use app\process\BaseCronTask;
use RuntimeException;
use support\Log;
use Yansongda\Supports\Collection;

/**
 * 支付状态补查定时任务
 * 每5分钟扫描待查单记录，主动向第三方查单
 */
class PaySyncPendingTransactionTask extends BaseCronTask
{
    protected function getTaskName(): string
    {
        return 'pay_sync_pending_transaction';
    }

    protected function handle(): ?string
    {
        // 5 分钟前的记录
        $since = date('Y-m-d H:i:s', time() - 300);
        $txns = (new PayTransactionDep())->getPendingCheck($since, 100);

        $checked = 0;
        foreach ($txns as $txn) {
            try {
                $this->checkTransaction($txn);
                $checked++;
            } catch (\Throwable $e) {
                Log::error("[PaySyncPendingTransaction] 查单失败 txn_no={$txn['transaction_no']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $checked > 0 ? "补查了 {$checked} 笔交易" : null;
    }

    private function checkTransaction(array $txn): void
    {
        $channelId = $txn['channel_id'] ?? 0;
        $channel = $txn['channel'] ?? PayEnum::CHANNEL_WECHAT;

        // 调用第三方查单
        try {
            $queryResult = $this->queryThirdPartyOrder($channel, $channelId, $txn);
        } catch (RuntimeException $e) {
            Log::warning("[PaySyncPendingTransaction] 第三方查单异常 txn_no={$txn['transaction_no']}", [
                'error' => $e->getMessage(),
            ]);
            return; // 第三方查询超时/异常，跳过本次
        }

        // 1. 第三方确认已支付 → 触发支付成功处理
        if ($this->isThirdPartyPaid($queryResult)) {
            $tradeNo = $this->extractTradeNo($queryResult);
            Log::info("[PaySyncPendingTransaction] 第三方已支付，触发支付成功 txn_no={$txn['transaction_no']}", [
                'trade_no' => $tradeNo,
            ]);

            $notifyModule = new PayNotifyModule();
            $notifyModule->handlePaySuccess($txn['transaction_no'], $tradeNo, $channel, [
                'out_trade_no' => $txn['transaction_no'],
                'trade_no'     => $tradeNo,
                'paid_time'    => date('Y-m-d H:i:s'),
                'source'       => 'cron_sync',
            ]);
            return;
        }

        // 2. 第三方确认未支付 → 保持现状，等超时关单
        Log::info("[PaySyncPendingTransaction] 第三方未支付，保持现状 txn_no={$txn['transaction_no']}", [
            'result' => is_object($queryResult) ? $queryResult->toArray() : $queryResult,
        ]);
    }

    /**
     * 调用第三方查单
     */
    private function queryThirdPartyOrder(int $channel, int $channelId, array $txn): mixed
    {
        if ($channel === PayEnum::CHANNEL_WECHAT) {
            return $this->queryWechatOrder($channelId, $txn);
        }

        if ($channel === PayEnum::CHANNEL_ALIPAY) {
            return $this->queryAlipayOrder($channelId, $txn);
        }

        throw new RuntimeException('不支持的支付渠道');
    }

    /**
     * 微信查单
     */
    private function queryWechatOrder(int $channelId, array $txn): mixed
    {
        $sdk = new PaySdk();
        $sdk->initWechat($channelId);

        $params = [];

        if (!empty($txn['trade_no'])) {
            $params['transaction_id'] = $txn['trade_no'];
        } else {
            $params['out_trade_no'] = $txn['transaction_no'];
        }

        return \Yansongda\Pay\Pay::wechat()->query($params);
    }

    /**
     * 支付宝查单
     */
    private function queryAlipayOrder(int $channelId, array $txn): mixed
    {
        $sdk = new PaySdk();
        $sdk->initAlipay($channelId);

        $params = [];

        if (!empty($txn['trade_no'])) {
            $params['trade_no'] = $txn['trade_no'];
        } else {
            $params['out_trade_no'] = $txn['transaction_no'];
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

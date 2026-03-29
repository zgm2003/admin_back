<?php

namespace app\module\Pay;

use app\dep\Pay\OrderDep;
use app\dep\Pay\OrderFulfillmentDep;
use app\dep\Pay\PayTransactionDep;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use app\module\BaseModule;
use app\service\Common\RedisLock;
use app\service\Pay\OrderNoGenerator;
use app\service\Pay\WalletService;
use RuntimeException;
use support\Log;
use Webman\RedisQueue\Client as RedisQueue;
use Yansongda\Supports\Collection;

/**
 * 支付核心模块
 * 负责：支付成功后的核心履约逻辑、状态推进、订单关闭
 * 事务边界由队列消费者（PayOrderFulfillment）调用时统一管理
 */
class PayModule extends BaseModule
{
    // ==================== 履约处理（由队列调用）====================

    /** 充值履约（由 PayOrderFulfillment 队列调用） */
    public function fulfillRecharge(array $fulfill): void
    {
        $payload = $this->decodeFulfillPayload($fulfill['request_payload'] ?? null);
        $amount = $payload['amount'] ?? 0;

        $this->withTransaction(function () use ($fulfill, $amount) {
            // 幂等 + 入账
            $walletSvc = new WalletService();
            $walletSvc->creditRecharge(
                $fulfill['user_id'],
                $amount,
                $fulfill['order_no'],
                $fulfill['order_id'],
                $fulfill['id'],
            );

            // 更新履约状态
            $this->dep(OrderFulfillmentDep::class)->update($fulfill['id'], [
                'status'      => PayEnum::FULFILL_SUCCESS,
                'executed_at' => date('Y-m-d H:i:s'),
                'result_payload' => json_encode(['wallet_credited' => true], JSON_UNESCAPED_UNICODE),
            ]);

            // 更新订单业务状态
            $this->dep(OrderDep::class)->update($fulfill['order_id'], [
                'biz_status'  => PayEnum::BIZ_STATUS_SUCCESS,
                'biz_done_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    /** 消费履约（P1 扩展） */
    public function fulfillConsume(array $fulfill): void
    {
        $payload = $this->decodeFulfillPayload($fulfill['request_payload'] ?? null);
        $bizActionNo = "FULFILL:CONSUME:{$fulfill['order_no']}";

        $walletSvc = new WalletService();
        if ($walletSvc->hasProcessed($bizActionNo)) {
            return;
        }

        $this->withTransaction(function () use ($fulfill, $payload, $bizActionNo) {
            $bizId = $fulfill['biz_id'] ?? 0;
            $amount = $payload['amount'] ?? 0;

            $result = $walletSvc->debitConsume(
                $fulfill['user_id'],
                $amount,
                $bizActionNo,
                $fulfill['order_id'],
                $fulfill['order_no'],
            );

            $this->dep(OrderFulfillmentDep::class)->update($fulfill['id'], [
                'status' => $result ? PayEnum::FULFILL_SUCCESS : PayEnum::FULFILL_FAILED,
                'executed_at' => $result ? date('Y-m-d H:i:s') : null,
                'result_payload' => json_encode(['consume_result' => $result], JSON_UNESCAPED_UNICODE),
                'last_error' => $result ? '' : '钱包余额不足',
            ]);

            $this->dep(OrderDep::class)->update($fulfill['order_id'], [
                'biz_status' => $result ? PayEnum::BIZ_STATUS_SUCCESS : PayEnum::BIZ_STATUS_FAILED,
                'biz_done_at' => $result ? date('Y-m-d H:i:s') : null,
            ]);
        });
    }

    /** 商品履约（P2 扩展） */
    public function fulfillGoods(array $fulfill): void
    {
        $this->withTransaction(function () use ($fulfill) {
            $this->dep(OrderFulfillmentDep::class)->update($fulfill['id'], [
                'status'      => PayEnum::FULFILL_SUCCESS,
                'executed_at' => date('Y-m-d H:i:s'),
            ]);

            $this->dep(OrderDep::class)->update($fulfill['order_id'], [
                'biz_status'  => PayEnum::BIZ_STATUS_SUCCESS,
                'biz_done_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    // ==================== 内部辅助方法 ====================

    /** 创建履约记录并投递队列 */
    public function createFulfillmentAndDispatch(array $order, int $actionType, array $extraPayload = []): void
    {
        $idempotencyKey = "FULFILL:RECHARGE:{$order['order_no']}";
        if ($actionType === PayEnum::FULFILL_ACTION_CONSUME) {
            $idempotencyKey = "FULFILL:CONSUME:{$order['order_no']}";
        } elseif ($actionType === PayEnum::FULFILL_ACTION_GOODS) {
            $idempotencyKey = "FULFILL:GOODS:{$order['order_no']}";
        }

        $existFulfill = $this->dep(OrderFulfillmentDep::class)->findByIdempotencyKey($idempotencyKey);
        if ($existFulfill) {
            return;
        }

        $fulfillNo = OrderNoGenerator::fulfill();
        $requestPayload = array_merge([
            'order_id'    => $order['id'],
            'order_no'    => $order['order_no'],
            'user_id'     => $order['user_id'],
            'amount'      => $order['pay_amount'],
            'biz_type'    => $order['biz_type'] ?? '',
            'biz_id'      => $order['biz_id'] ?? 0,
        ], $extraPayload);

        $sourceTxnId = $order['success_transaction_id'] ?? 0;

        $fulfillId = $this->dep(OrderFulfillmentDep::class)->add([
            'fulfill_no'      => $fulfillNo,
            'order_id'        => $order['id'],
            'order_no'        => $order['order_no'],
            'user_id'         => $order['user_id'],
            'biz_type'        => $order['biz_type'] ?? '',
            'biz_id'          => $order['biz_id'] ?? 0,
            'action_type'     => $actionType,
            'source_txn_id'   => $sourceTxnId,
            'idempotency_key' => $idempotencyKey,
            'status'          => PayEnum::FULFILL_PENDING,
            'retry_count'     => 0,
            'request_payload' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE),
        ]);

        // 投递慢队列
        RedisQueue::connection('default')->send('pay_order_fulfillment', [
            'fulfill_id' => $fulfillId,
            'order_id'   => $order['id'],
        ]);
    }

    private function decodeFulfillPayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 先查第三方，再决定补单或关单。
     * 返回：paid / closed / deferred / skipped / missing
     */
    public function syncPaidOrCloseOrder(string $orderNo, string $closeReason, string $source = 'system_sync'): string
    {
        $lockKey = "pay_create_txn_{$orderNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        if (!$lockVal) {
            return 'deferred';
        }

        try {
            $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);
            if (!$order) {
                return 'missing';
            }

            if (!in_array((int) $order->pay_status, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING], true)) {
                return 'skipped';
            }

            $txn = $this->dep(PayTransactionDep::class)->findLastActive((int) $order->id);
            if (!$txn) {
                return $this->closeOrderWithinLock($orderNo, $closeReason) ? 'closed' : 'skipped';
            }

            try {
                $queryResult = $this->queryThirdPartyOrder(
                    (int) $txn->channel,
                    (int) $txn->channel_id,
                    (string) $txn->transaction_no,
                    (string) ($txn->trade_no ?? '')
                );
            } catch (\Throwable $e) {
                Log::warning("[PayModule] 第三方查单异常 order_no={$orderNo}", [
                    'error' => $e->getMessage(),
                ]);
                return 'deferred';
            }

            if ($this->isThirdPartyPaid($queryResult)) {
                $tradeNo = $this->extractTradeNo($queryResult);
                (new PayNotifyModule())->handlePaySuccess((string) $txn->transaction_no, $tradeNo, (int) $txn->channel, [
                    'out_trade_no' => (string) $txn->transaction_no,
                    'trade_no' => $tradeNo,
                    'paid_time' => date('Y-m-d H:i:s'),
                    'source' => $source,
                ]);
                return 'paid';
            }

            if (!$this->closeOrderWithinLock($orderNo, $closeReason)) {
                return 'skipped';
            }

            $this->closeThirdPartyPaymentSafely((int) $txn->channel_id, (int) $txn->channel, (string) $txn->transaction_no);

            return 'closed';
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    private function closeOrderWithinLock(string $orderNo, string $reason): bool
    {
        $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);
        if (!$order) {
            return false;
        }

        $currentStatus = (int) $order->pay_status;
        if (!in_array($currentStatus, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING], true)) {
            return false;
        }

        $closed = $this->dep(OrderDep::class)->closeOrder((int) $order->id, $currentStatus, $reason);
        if (!$closed) {
            return false;
        }

        $txn = $this->dep(PayTransactionDep::class)->findLastActive((int) $order->id);
        if ($txn) {
            $this->dep(PayTransactionDep::class)->update((int) $txn->id, [
                'status' => PayEnum::TXN_CLOSED,
                'closed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    private function queryThirdPartyOrder(int $channel, int $channelId, string $outTradeNo, string $tradeNo): mixed
    {
        if ($channel === PayEnum::CHANNEL_WECHAT) {
            return $this->queryWechatOrder($channelId, $outTradeNo, $tradeNo);
        }

        if ($channel === PayEnum::CHANNEL_ALIPAY) {
            return $this->queryAlipayOrder($channelId, $outTradeNo, $tradeNo);
        }

        throw new RuntimeException('不支持的支付渠道');
    }

    private function queryWechatOrder(int $channelId, string $outTradeNo, string $tradeNo): mixed
    {
        PaySdk::initWechat($channelId);

        $params = ['out_trade_no' => $outTradeNo];
        if ($tradeNo !== '') {
            $params['transaction_id'] = $tradeNo;
        }

        return \Yansongda\Pay\Pay::wechat()->query($params);
    }

    private function queryAlipayOrder(int $channelId, string $outTradeNo, string $tradeNo): mixed
    {
        PaySdk::initAlipay($channelId);

        $params = ['out_trade_no' => $outTradeNo];
        if ($tradeNo !== '') {
            $params['trade_no'] = $tradeNo;
        }

        return \Yansongda\Pay\Pay::alipay()->query($params);
    }

    public function closeThirdPartyPaymentSafely(int $channelId, int $channel, string $transactionNo): void
    {
        if ($channelId <= 0 || $channel <= 0 || $transactionNo === '') {
            return;
        }

        $sdk = new PaySdk();
        $payload = ['out_trade_no' => $transactionNo];

        try {
            if ($channel === PayEnum::CHANNEL_WECHAT) {
                $sdk->wechatClose($channelId, $payload);
                return;
            }

            if ($channel === PayEnum::CHANNEL_ALIPAY) {
                $sdk->alipayClose($channelId, $payload);
            }
        } catch (\Throwable $e) {
            Log::warning('[PayModule] 第三方关单失败', [
                'channel_id' => $channelId,
                'channel' => $channel,
                'transaction_no' => $transactionNo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isThirdPartyPaid(mixed $result): bool
    {
        if ($result instanceof Collection) {
            $result = $result->toArray();
        }

        if (!is_array($result)) {
            return false;
        }

        if (($result['trade_state'] ?? '') === 'SUCCESS') {
            return true;
        }

        return in_array($result['trade_status'] ?? '', ['TRADE_SUCCESS', 'TRADE_FINISHED'], true);
    }

    private function extractTradeNo(mixed $result): string
    {
        if ($result instanceof Collection) {
            $result = $result->toArray();
        }

        if (!is_array($result)) {
            return '';
        }

        return (string) ($result['transaction_id'] ?? $result['trade_no'] ?? '');
    }
}

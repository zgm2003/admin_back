<?php

namespace app\service\Pay;

use app\dep\Pay\OrderDep;
use app\dep\Pay\OrderFulfillmentDep;
use app\dep\Pay\PayTransactionDep;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use app\service\Common\RedisLock;
use RuntimeException;
use support\Db;
use support\Log;
use Webman\RedisQueue\Client as RedisQueue;
use Yansongda\Supports\Collection;

class PayDomainService
{
    private OrderDep $orderDep;
    private OrderFulfillmentDep $orderFulfillmentDep;
    private PayTransactionDep $payTransactionDep;
    private WalletService $walletService;

    public function __construct()
    {
        $this->orderDep = new OrderDep();
        $this->orderFulfillmentDep = new OrderFulfillmentDep();
        $this->payTransactionDep = new PayTransactionDep();
        $this->walletService = new WalletService();
    }

    public function fulfillRecharge(array $fulfill): void
    {
        $payload = $this->decodeFulfillPayload($fulfill['request_payload'] ?? null);
        $amount = (int) ($payload['amount'] ?? 0);

        $this->withTransaction(function () use ($fulfill, $amount) {
            $this->walletService->creditRecharge(
                (int) $fulfill['user_id'],
                $amount,
                (string) $fulfill['order_no'],
                (int) $fulfill['order_id'],
                (int) $fulfill['id'],
            );

            $this->orderFulfillmentDep->update((int) $fulfill['id'], [
                'status' => PayEnum::FULFILL_SUCCESS,
                'executed_at' => date('Y-m-d H:i:s'),
                'result_payload' => json_encode(['wallet_credited' => true], JSON_UNESCAPED_UNICODE),
                'last_error' => '',
            ]);

            $this->orderDep->update((int) $fulfill['order_id'], [
                'biz_status' => PayEnum::BIZ_STATUS_SUCCESS,
                'biz_done_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function fulfillConsume(array $fulfill): void
    {
        $payload = $this->decodeFulfillPayload($fulfill['request_payload'] ?? null);
        $bizActionNo = "FULFILL:CONSUME:{$fulfill['order_no']}";

        if ($this->walletService->hasProcessed($bizActionNo)) {
            return;
        }

        $this->withTransaction(function () use ($fulfill, $payload, $bizActionNo) {
            $amount = (int) ($payload['amount'] ?? 0);

            $result = $this->walletService->debitConsume(
                (int) $fulfill['user_id'],
                $amount,
                $bizActionNo,
                (int) $fulfill['order_id'],
                (string) $fulfill['order_no'],
            );

            $this->orderFulfillmentDep->update((int) $fulfill['id'], [
                'status' => $result ? PayEnum::FULFILL_SUCCESS : PayEnum::FULFILL_FAILED,
                'executed_at' => $result ? date('Y-m-d H:i:s') : null,
                'result_payload' => json_encode(['consume_result' => $result], JSON_UNESCAPED_UNICODE),
                'last_error' => $result ? '' : '钱包余额不足',
            ]);

            $this->orderDep->update((int) $fulfill['order_id'], [
                'biz_status' => $result ? PayEnum::BIZ_STATUS_SUCCESS : PayEnum::BIZ_STATUS_FAILED,
                'biz_done_at' => $result ? date('Y-m-d H:i:s') : null,
            ]);
        });
    }

    public function fulfillGoods(array $fulfill): void
    {
        $this->withTransaction(function () use ($fulfill) {
            $this->orderFulfillmentDep->update((int) $fulfill['id'], [
                'status' => PayEnum::FULFILL_SUCCESS,
                'executed_at' => date('Y-m-d H:i:s'),
                'last_error' => '',
            ]);

            $this->orderDep->update((int) $fulfill['order_id'], [
                'biz_status' => PayEnum::BIZ_STATUS_SUCCESS,
                'biz_done_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function createFulfillmentAndDispatch(array $order, int $actionType, array $extraPayload = []): void
    {
        $idempotencyKey = match ($actionType) {
            PayEnum::FULFILL_ACTION_CONSUME => "FULFILL:CONSUME:{$order['order_no']}",
            PayEnum::FULFILL_ACTION_GOODS => "FULFILL:GOODS:{$order['order_no']}",
            default => "FULFILL:RECHARGE:{$order['order_no']}",
        };

        $existFulfill = $this->orderFulfillmentDep->findByIdempotencyKey($idempotencyKey);
        if ($existFulfill) {
            return;
        }

        $fulfillId = $this->orderFulfillmentDep->add([
            'fulfill_no' => OrderNoGenerator::fulfill(),
            'order_id' => $order['id'],
            'order_no' => $order['order_no'],
            'user_id' => $order['user_id'],
            'biz_type' => $order['biz_type'] ?? '',
            'biz_id' => $order['biz_id'] ?? 0,
            'action_type' => $actionType,
            'source_txn_id' => $order['success_transaction_id'] ?? 0,
            'idempotency_key' => $idempotencyKey,
            'status' => PayEnum::FULFILL_PENDING,
            'retry_count' => 0,
            'request_payload' => json_encode(array_merge([
                'order_id' => $order['id'],
                'order_no' => $order['order_no'],
                'user_id' => $order['user_id'],
                'amount' => $order['pay_amount'],
                'biz_type' => $order['biz_type'] ?? '',
                'biz_id' => $order['biz_id'] ?? 0,
            ], $extraPayload), JSON_UNESCAPED_UNICODE),
        ]);

        RedisQueue::connection('default')->send('pay_order_fulfillment', [
            'fulfill_id' => $fulfillId,
            'order_id' => $order['id'],
        ]);
    }

    public function handlePaySuccess(string $transactionNo, string $tradeNo, int $channel, array $rawData): array
    {
        $lockKey = "pay_notify_{$transactionNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        if (!$lockVal) {
            throw new RuntimeException('正在处理中');
        }

        try {
            $txn = $this->payTransactionDep->findByTransactionNo($transactionNo);
            if (!$txn) {
                $this->log("支付流水不存在，跳过 transactionNo={$transactionNo}");

                return ['status' => 'ignored', 'message' => '支付流水不存在'];
            }

            if ((int) $txn->status === PayEnum::TXN_SUCCESS) {
                $this->log("交易已成功，跳过 transactionNo={$transactionNo}");

                return ['status' => 'ignored', 'message' => '交易已成功'];
            }

            $this->withTransaction(function () use ($txn, $tradeNo, $rawData) {
                $affected = $this->payTransactionDep->updateStatus((int) $txn->id, (int) $txn->status, PayEnum::TXN_SUCCESS, [
                    'trade_no' => $tradeNo,
                    'trade_status' => 'SUCCESS',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'raw_notify' => json_encode($rawData, JSON_UNESCAPED_UNICODE),
                ]);

                if ($affected === 0) {
                    $freshTxn = $this->payTransactionDep->find((int) $txn->id);
                    if ($freshTxn && (int) $freshTxn->status === PayEnum::TXN_SUCCESS) {
                        return;
                    }

                    throw new RuntimeException('支付流水状态更新失败');
                }

                $order = $this->orderDep->find((int) $txn->order_id);
                if (!$order) {
                    return;
                }

                if (
                    (int) $order->pay_status !== PayEnum::PAY_STATUS_PAID
                    && PayEnum::canTransitPayStatus((int) $order->pay_status, PayEnum::PAY_STATUS_PAID)
                ) {
                    $this->orderDep->updatePayStatus((int) $order->id, (int) $order->pay_status, PayEnum::PAY_STATUS_PAID, [
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_method' => (string) $txn->pay_method,
                        'channel_id' => (int) $txn->channel_id,
                        'success_transaction_id' => (int) $txn->id,
                    ]);
                    $order->pay_status = PayEnum::PAY_STATUS_PAID;
                } elseif ((int) $order->success_transaction_id === 0) {
                    $this->orderDep->update((int) $order->id, [
                        'success_transaction_id' => (int) $txn->id,
                        'channel_id' => (int) $txn->channel_id,
                        'pay_method' => (string) $txn->pay_method,
                    ]);
                }

                if (PayEnum::canTransitBizStatus((int) $order->biz_status, PayEnum::BIZ_STATUS_PENDING)) {
                    $this->orderDep->updateBizStatus((int) $order->id, (int) $order->biz_status, PayEnum::BIZ_STATUS_PENDING);
                }

                $this->createFulfillmentAndDispatch(array_merge($order->toArray(), [
                    'success_transaction_id' => (int) $txn->id,
                    'channel_id' => (int) $txn->channel_id,
                    'pay_method' => (string) $txn->pay_method,
                ]), $this->resolveFulfillmentActionType((int) $order->order_type));
            });

            return ['status' => 'success', 'message' => '支付成功'];
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    public function syncPaidOrCloseOrder(string $orderNo, string $closeReason, string $source = 'system_sync'): string
    {
        $lockKey = "pay_create_txn_{$orderNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        if (!$lockVal) {
            return 'deferred';
        }

        try {
            $order = $this->orderDep->findByOrderNo($orderNo);
            if (!$order) {
                return 'missing';
            }

            if (!in_array((int) $order->pay_status, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING], true)) {
                return 'skipped';
            }

            $txn = $this->payTransactionDep->findLastActive((int) $order->id);
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
                Log::warning("[PayDomainService] 第三方查单异常 order_no={$orderNo}", ['error' => $e->getMessage()]);
                return 'deferred';
            }

            if ($this->isThirdPartyPaid($queryResult)) {
                $tradeNo = $this->extractTradeNo($queryResult);
                $result = $this->handlePaySuccess((string) $txn->transaction_no, $tradeNo, (int) $txn->channel, [
                    'out_trade_no' => (string) $txn->transaction_no,
                    'trade_no' => $tradeNo,
                    'paid_time' => date('Y-m-d H:i:s'),
                    'source' => $source,
                ]);

                return in_array($result['status'], ['success', 'ignored'], true) ? 'paid' : 'deferred';
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
            Log::warning('[PayDomainService] 第三方关单失败', [
                'channel_id' => $channelId,
                'channel' => $channel,
                'transaction_no' => $transactionNo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function resolveFulfillmentActionType(int $orderType): int
    {
        return match ($orderType) {
            PayEnum::TYPE_CONSUME => PayEnum::FULFILL_ACTION_CONSUME,
            PayEnum::TYPE_GOODS => PayEnum::FULFILL_ACTION_GOODS,
            default => PayEnum::FULFILL_ACTION_RECHARGE,
        };
    }

    private function closeOrderWithinLock(string $orderNo, string $reason): bool
    {
        $order = $this->orderDep->findByOrderNo($orderNo);
        if (!$order) {
            return false;
        }

        $currentStatus = (int) $order->pay_status;
        if (!in_array($currentStatus, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING], true)) {
            return false;
        }

        $closed = $this->orderDep->closeOrder((int) $order->id, $currentStatus, $reason);
        if (!$closed) {
            return false;
        }

        $txn = $this->payTransactionDep->findLastActive((int) $order->id);
        if ($txn) {
            $this->payTransactionDep->update((int) $txn->id, [
                'status' => PayEnum::TXN_CLOSED,
                'closed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    private function queryThirdPartyOrder(int $channel, int $channelId, string $outTradeNo, string $tradeNo): mixed
    {
        if ($channel === PayEnum::CHANNEL_WECHAT) {
            PaySdk::initWechat($channelId);
            $params = ['out_trade_no' => $outTradeNo];
            if ($tradeNo !== '') {
                $params['transaction_id'] = $tradeNo;
            }

            return \Yansongda\Pay\Pay::wechat()->query($params);
        }

        if ($channel === PayEnum::CHANNEL_ALIPAY) {
            PaySdk::initAlipay($channelId);
            $params = ['out_trade_no' => $outTradeNo];
            if ($tradeNo !== '') {
                $params['trade_no'] = $tradeNo;
            }

            return \Yansongda\Pay\Pay::alipay()->query($params);
        }

        throw new RuntimeException('不支持的支付渠道');
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

    private function withTransaction(callable $callback)
    {
        Db::beginTransaction();
        try {
            $result = $callback();
            Db::commit();
            return $result;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    private function log(string $msg, array $context = []): void
    {
        Log::info("[PayDomainService] {$msg}", $context);
    }
}

<?php

namespace app\module\Pay;

use app\dep\Pay\OrderDep;
use app\dep\Pay\OrderItemDep;
use app\dep\Pay\OrderFulfillmentDep;
use app\dep\Pay\PayChannelDep;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\service\Common\RedisLock;
use app\service\Pay\OrderNoGenerator;
use app\service\Pay\WalletService;
use app\validate\Pay\PayTransactionValidate;
use Webman\RedisQueue\Client as RedisQueue;

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
        $payload = $fulfill['request_payload'] ? json_decode($fulfill['request_payload'], true) : [];
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
        $payload = $fulfill['request_payload'] ? json_decode($fulfill['request_payload'], true) : [];
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
        if ($existFulfill && $existFulfill->status !== PayEnum::FULFILL_PENDING) {
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

    /** 订单关闭（内部用，含第三方查单） */
    public function closeWithCheck(string $orderNo, string $reason): void
    {
        $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);
        if (!$order) {
            return;
        }

        $currentStatus = $order->pay_status;
        if (!in_array($currentStatus, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING])) {
            return;
        }

        $lockKey = "pay_create_txn_{$orderNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        if (!$lockVal) {
            return;
        }

        try {
            $this->dep(OrderDep::class)->closeOrder($order->id, $currentStatus, $reason);
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }
}

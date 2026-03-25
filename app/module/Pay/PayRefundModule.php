<?php

namespace app\module\Pay;

use app\dep\Pay\PayRefundDep;
use app\dep\Pay\OrderDep;
use app\dep\Pay\PayTransactionDep;
use app\dep\Pay\PayChannelDep;
use app\dep\Pay\WalletTransactionDep;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\service\Common\RedisLock;
use app\service\Pay\OrderNoGenerator;
use app\service\Pay\PayService;
use app\service\Pay\WalletService;
use app\validate\Pay\PayRefundValidate;
use RuntimeException;
use support\Log;

/**
 * 退款管理模块
 */
class PayRefundModule extends BaseModule
{
    /** 初始化 */
    public function init($request): array
    {
        $dict = $this->svc(DictService::class)
            ->setPayChannelArr()
            ->getDict();

        $dict['refund_record_status_arr'] = DictService::enumToDict(PayEnum::$refundRecordStatusArr);

        return self::success(['dict' => $dict]);
    }

    /** 列表 */
    public function list($request): array
    {
        $param = $this->validate($request, PayRefundValidate::list());
        $res = $this->dep(PayRefundDep::class)->list($param);

        $list = $res->map(function ($item) {
            return [
                'id'              => $item->id,
                'refund_no'       => $item->refund_no,
                'order_no'        => $item->order_no,
                'channel'         => $item->channel,
                'channel_text'     => PayEnum::$channelArr[$item->channel] ?? '',
                'refund_amount'   => $item->refund_amount,
                'status'          => $item->status,
                'status_text'     => PayEnum::$refundRecordStatusArr[$item->status] ?? '',
                'reason'          => $item->reason,
                'operator_id'    => $item->operator_id,
                'refunded_at'     => $item->refunded_at,
                'created_at'      => $item->created_at,
            ];
        });

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /** 详情 */
    public function detail($request): array
    {
        $param = $this->validate($request, PayRefundValidate::detail());
        $refund = $this->dep(PayRefundDep::class)->getOrFail($param['id']);

        $order = null;
        if ($refund->order_id) {
            $o = $this->dep(OrderDep::class)->find($refund->order_id);
            if ($o) {
                $order = [
                    'id'         => $o->id,
                    'order_no'   => $o->order_no,
                    'title'      => $o->title,
                    'pay_amount' => $o->pay_amount,
                    'pay_status' => $o->pay_status,
                ];
            }
        }

        return self::success([
            'refund' => [
                'id'                => $refund->id,
                'refund_no'         => $refund->refund_no,
                'order_no'          => $refund->order_no,
                'channel'           => $refund->channel,
                'refund_amount'    => $refund->refund_amount,
                'wallet_freeze_amount' => $refund->wallet_freeze_amount,
                'trade_refund_no'  => $refund->trade_refund_no,
                'status'            => $refund->status,
                'status_text'       => PayEnum::$refundRecordStatusArr[$refund->status] ?? '',
                'reason'            => $refund->reason,
                'fail_reason'       => $refund->fail_reason,
                'operator_id'       => $refund->operator_id,
                'frozen_at'         => $refund->frozen_at,
                'refunded_at'       => $refund->refunded_at,
                'raw_request'       => $refund->raw_request ? json_decode($refund->raw_request, true) : [],
                'raw_notify'        => $refund->raw_notify ? json_decode($refund->raw_notify, true) : [],
                'remark'            => $refund->remark,
                'created_at'        => $refund->created_at,
            ],
            'order' => $order,
        ]);
    }

    /** 申请退款 */
    public function apply($request): array
    {
        $param = $this->validate($request, PayRefundValidate::apply());
        $operatorId = (int) ($request->user_id ?? 0);

        $orderDep = new OrderDep();
        $order = $orderDep->findOrFail($param['order_id']);

        // 业务校验
        self::throwIf($order->pay_status !== PayEnum::PAY_STATUS_PAID, '订单未支付');
        self::throwIf($order->biz_status !== PayEnum::BIZ_STATUS_SUCCESS, '订单未履约成功');
        self::throwIf($order->refund_status === PayEnum::REFUND_STATUS_FULL, '已全额退款');

        $refundAmount = (int) $param['refund_amount'];
        $remainAmount = $order->pay_amount - $order->refunded_amount;
        self::throwIf($refundAmount <= 0, '退款金额必须大于0');
        self::throwIf($refundAmount > $remainAmount, "可退款余额不足（最大 {$remainAmount} 分）");

        $walletSvc = new WalletService();
        $wallet = $walletSvc->getOrCreateWallet($order->user_id);

        // 检查余额是否足够冻结
        self::throwIf($wallet['balance'] < $refundAmount, '用户钱包余额不足，请人工处理');

        $refundNo = OrderNoGenerator::refund();
        $lockKey = "wallet_refund_freeze_{$order->user_id}";
        $lockVal = RedisLock::lock($lockKey, 30);
        self::throwIf(!$lockVal, '正在处理中，请稍后');

        try {
            return $this->withTransaction(function () use ($order, $refundAmount, $refundNo, $param, $operatorId, $wallet, $walletSvc, $remainAmount, $orderDep) {
                // 冻结钱包
                $frozen = $walletSvc->freezeForRefund(
                    $order->user_id,
                    $refundAmount,
                    $refundNo,
                    $order->id,
                    $order->order_no,
                );
                self::throwUnless($frozen, '冻结失败，请重试');

                // 创建退款记录
                $txn = $this->dep(PayTransactionDep::class)->find($param['transaction_id'] ?? $order->success_transaction_id);
                $channelId = $txn ? $txn->channel_id : $order->channel_id;
                $channel = $txn ? $txn->channel : PayEnum::CHANNEL_WECHAT;

                $refundId = $this->dep(PayRefundDep::class)->add([
                    'refund_no'            => $refundNo,
                    'order_id'             => $order->id,
                    'order_no'             => $order->order_no,
                    'user_id'              => $order->user_id,
                    'transaction_id'        => $param['transaction_id'] ?? $order->success_transaction_id,
                    'channel_id'           => $channelId,
                    'channel'              => $channel,
                    'refund_amount'        => $refundAmount,
                    'wallet_freeze_amount' => $refundAmount,
                    'status'               => PayEnum::REFUND_CREATED,
                    'reason'              => $param['reason'] ?? '',
                    'operator_id'          => $operatorId,
                    'frozen_at'           => date('Y-m-d H:i:s'),
                    'raw_request'         => json_encode($param, JSON_UNESCAPED_UNICODE),
                ]);

                // 更新订单退款状态
                $newRefundStatus = $refundAmount >= $remainAmount
                    ? PayEnum::REFUND_STATUS_FULL
                    : ($order->refunded_amount > 0 ? PayEnum::REFUND_STATUS_PARTIAL : PayEnum::REFUND_STATUS_ING);
                $orderDep->update($order->id, ['refund_status' => $newRefundStatus]);

                Log::info('[PayRefund] 退款申请成功', [
                    'refund_no'     => $refundNo,
                    'order_no'      => $order->order_no,
                    'refund_amount' => $refundAmount,
                    'operator_id'   => $operatorId,
                ]);

                // 调用第三方退款接口
                $this->doRefundRequest($refundId, $refundNo, $channel, $channelId, $txn, $refundAmount, $order->order_no);

                return self::success(['refund_no' => $refundNo, 'refund_id' => $refundId]);
            });
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    /**
     * 调用第三方退款接口
     */
    private function doRefundRequest(
        int $refundId,
        string $refundNo,
        int $channel,
        int $channelId,
        ?object $txn,
        int $refundAmount,
        string $orderNo
    ): void {
        try {
            $channelModel = (new PayChannelDep())->findActive($channelId);
            if (!$channelModel) {
                throw new RuntimeException('支付渠道配置不存在');
            }

            $params = [
                'out_trade_no'  => $orderNo,
                'out_refund_no' => $refundNo,
                'total_fee'     => $txn ? $txn->amount : $refundAmount,
                'refund_fee'   => $refundAmount,
                'refund_desc'  => "订单退款: {$orderNo}",
            ];

            if ($txn && $txn->trade_no) {
                $params['transaction_id'] = $txn->trade_no;
            }

            $result = match ($channel) {
                PayEnum::CHANNEL_WECHAT => $this->callWechatRefund($channelModel, $params),
                PayEnum::CHANNEL_ALIPAY => $this->callAlipayRefund($channelModel, $params),
                default => throw new RuntimeException('不支持的支付渠道'),
            };

            // 更新退款状态为退款中
            $this->dep(PayRefundDep::class)->update($refundId, [
                'status' => PayEnum::REFUND_ING,
                'trade_refund_no' => $result['trade_refund_no'] ?? '',
            ]);

            Log::info('[PayRefund] 第三方退款调用成功', [
                'refund_no'        => $refundNo,
                'trade_refund_no' => $result['trade_refund_no'] ?? '',
            ]);

        } catch (RuntimeException $e) {
            // 第三方退款调用失败，回滚冻结（已在事务中，会自动回滚）
            Log::error('[PayRefund] 第三方退款调用失败', [
                'refund_no' => $refundNo,
                'error'     => $e->getMessage(),
            ]);

            $this->dep(PayRefundDep::class)->update($refundId, [
                'status'      => PayEnum::REFUND_FAILED,
                'fail_reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 调用微信退款接口
     */
    private function callWechatRefund(object $channel, array $params): array
    {
        $sdk = new PaySdk();
        $result = $sdk->wechatRefund($channel->id, $params);
        return [
            'trade_refund_no' => $result['refund_id'] ?? '',
        ];
    }

    /**
     * 调用支付宝退款接口
     */
    private function callAlipayRefund(object $channel, array $params): array
    {
        $sdk = new PaySdk();
        $result = $sdk->alipayRefund($channel->id, $params);
        return [
            'trade_refund_no' => $result['trade_no'] ?? '',
        ];
    }

    /**
     * 处理退款成功回调（内部调用）
     */
    public function handleRefundSuccess(string $refundNo, string $tradeRefundNo, array $rawData): array
    {
        $lockKey = "pay_refund_notify_{$refundNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        self::throwIf(!$lockVal, '正在处理中');

        try {
            return $this->withTransaction(function () use ($refundNo, $tradeRefundNo, $rawData) {
                $refund = $this->dep(PayRefundDep::class)->findByRefundNo($refundNo);

                if (!$refund) {
                    $this->log("退款记录不存在，跳过 refundNo={$refundNo}");
                    return self::success();
                }

                // 幂等：已成功则跳过
                if ($refund->status === PayEnum::REFUND_SUCCESS) {
                    $this->log("退款已成功，跳过 refundNo={$refundNo}");
                    return self::success();
                }

                // 更新退款状态
                $this->dep(PayRefundDep::class)->update($refund->id, [
                    'status'      => PayEnum::REFUND_SUCCESS,
                    'refunded_at' => date('Y-m-d H:i:s'),
                    'trade_refund_no' => $tradeRefundNo,
                    'raw_notify'  => json_encode($rawData, JSON_UNESCAPED_UNICODE),
                ]);

                // 解冻钱包并扣减冻结金额
                $walletSvc = new WalletService();
                $walletSvc->finalizeRefund(
                    $refund->user_id,
                    $refund->refund_amount,
                    "REFUND:CONFIRM:{$refundNo}",
                    $refund->order_id,
                    $refund->order_no,
                );

                // 更新订单退款状态
                $orderDep = new OrderDep();
                $order = $orderDep->find($refund->order_id);
                if ($order) {
                    $newRefundedAmount = $order->refunded_amount + $refund->refund_amount;
                    $newRefundStatus = $newRefundedAmount >= $order->pay_amount
                        ? PayEnum::REFUND_STATUS_FULL
                        : PayEnum::REFUND_STATUS_PARTIAL;

                    $orderDep->update($order->id, [
                        'refunded_amount' => $newRefundedAmount,
                        'refund_status'   => $newRefundStatus,
                    ]);
                }

                $this->log("退款成功 refundNo={$refundNo}");

                return self::success();
            });
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    private function log(string $msg, array $context = []): void
    {
        Log::info("[PayRefund] {$msg}", $context);
    }

    /**
     * 处理退款失败（内部调用）
     */
    public function handleRefundFail(string $refundNo, string $failReason, array $rawData = []): array
    {
        $lockKey = "pay_refund_fail_{$refundNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        self::throwIf(!$lockVal, '正在处理中');

        try {
            return $this->withTransaction(function () use ($refundNo, $failReason, $rawData) {
                $refund = $this->dep(PayRefundDep::class)->findByRefundNo($refundNo);

                if (!$refund) {
                    $this->log("退款记录不存在，跳过 refundNo={$refundNo}");
                    return self::success();
                }

                // 幂等：已失败/已成功则跳过
                if (in_array($refund->status, [PayEnum::REFUND_FAILED, PayEnum::REFUND_SUCCESS])) {
                    $this->log("退款已处理，跳过 refundNo={$refundNo}");
                    return self::success();
                }

                // 更新退款状态为失败
                $this->dep(PayRefundDep::class)->update($refund->id, [
                    'status'      => PayEnum::REFUND_FAILED,
                    'fail_reason' => $failReason,
                    'raw_notify'  => $rawData ? json_encode($rawData, JSON_UNESCAPED_UNICODE) : null,
                ]);

                // 解冻钱包（退回冻结金额）
                $walletSvc = new WalletService();
                $walletSvc->unfreezeRefund(
                    $refund->user_id,
                    $refund->refund_amount,
                    "REFUND:FAIL:{$refundNo}",
                    $refund->order_id,
                    $refund->order_no,
                );

                // 更新订单退款状态
                $orderDep = new OrderDep();
                $order = $orderDep->find($refund->order_id);
                if ($order) {
                    $newRefundStatus = $order->refunded_amount > 0
                        ? PayEnum::REFUND_STATUS_PARTIAL
                        : PayEnum::REFUND_STATUS_NONE;
                    $orderDep->update($order->id, [
                        'refund_status' => $newRefundStatus,
                    ]);
                }

                $this->log("退款失败处理完成 refundNo={$refundNo}, reason={$failReason}");

                return self::success();
            });
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }
}

<?php

namespace app\module\Pay;

use app\dep\Pay\PayTransactionDep;
use app\dep\Pay\PayRefundDep;
use app\dep\Pay\OrderDep;
use app\dep\Pay\OrderFulfillmentDep;
use app\dep\Pay\PayChannelDep;
use app\dep\Pay\PayNotifyLogDep;
use app\dep\Pay\WalletTransactionDep;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use app\module\BaseModule;
use app\service\Common\RedisLock;
use app\service\Pay\OrderNoGenerator;
use app\service\Pay\WalletService;
use RuntimeException;
use support\Log;
use support\Request;
use Yansongda\Pay\Pay;
use Yansongda\Supports\Collection;
use Webman\RedisQueue\Client as RedisQueue;

/**
 * 支付回调模块
 * 负责：微信/支付宝支付回调、退款回调的幂等处理、状态推进
 */
class PayNotifyModule extends BaseModule
{
    /** 微信支付回调 */
    public function wechat(Request $request): array
    {
        $ip = $request->getRealIp();
        $rawData = $request->all();

        $this->dispatchPayNotifyLog(PayEnum::CHANNEL_WECHAT, PayEnum::NOTIFY_PAY, $rawData, $ip);

        Log::info('[PayNotify] 微信回调原始数据', ['data' => $rawData]);

        try {
            // 从交易流水反查渠道（channel_id 存在），支持沙盒/生产自动识别
            $transactionNo = $rawData['out_trade_no'] ?? '';
            $channel = $transactionNo ? $this->findChannelByTransactionNo($transactionNo, PayEnum::CHANNEL_WECHAT) : null;
            if (!$channel) {
                $channel = $this->findWechatChannel();
            }
            if (!$channel) {
                throw new RuntimeException('未配置微信支付渠道');
            }

            // 初始化 SDK 并验签
            PaySdk::initWechat($channel->id);
            $data = Pay::wechat()->callback($rawData);

            if (!($data instanceof Collection)) {
                Log::warning('[PayNotify] 微信回调验签失败');
                return $this->wechatResponse(false, '签名验证失败');
            }

            // 处理支付成功
            $this->handlePaySuccessFromNotify($data->toArray(), PayEnum::CHANNEL_WECHAT);

            Log::info('[PayNotify] 微信回调处理成功', ['data' => $data->toArray()]);

            return $this->wechatResponse(true, 'OK');

        } catch (RuntimeException $e) {
            Log::error('[PayNotify] 微信回调处理异常', ['error' => $e->getMessage()]);
            return $this->wechatResponse(false, $e->getMessage());
        }
    }

    /** 支付宝支付回调 */
    public function alipay(Request $request): array
    {
        $ip = $request->getRealIp();
        $rawData = $request->all();

        $this->dispatchPayNotifyLog(PayEnum::CHANNEL_ALIPAY, PayEnum::NOTIFY_PAY, $rawData, $ip);

        Log::info('[PayNotify] 支付宝回调原始数据', ['data' => $rawData]);

        try {
            // 从交易流水反查渠道（channel_id 存在），支持沙盒/生产自动识别
            $transactionNo = $rawData['out_trade_no'] ?? '';
            $channel = $transactionNo ? $this->findChannelByTransactionNo($transactionNo, PayEnum::CHANNEL_ALIPAY) : null;
            if (!$channel) {
                $channel = $this->findAlipayChannel();
            }
            if (!$channel) {
                throw new RuntimeException('未配置支付宝渠道');
            }

            // 初始化 SDK 并验签
            PaySdk::initAlipay($channel->id);
            $data = Pay::alipay()->callback($rawData);

            if (!($data instanceof Collection)) {
                Log::warning('[PayNotify] 支付宝回调验签失败');
                return $this->alipayResponse(false, '签名验证失败');
            }

            // 处理支付成功
            $this->handlePaySuccessFromNotify($data->toArray(), PayEnum::CHANNEL_ALIPAY);

            Log::info('[PayNotify] 支付宝回调处理成功', ['data' => $data->toArray()]);

            return $this->alipayResponse(true, 'SUCCESS');

        } catch (RuntimeException $e) {
            Log::error('[PayNotify] 支付宝回调处理异常', ['error' => $e->getMessage()]);
            return $this->alipayResponse(false, $e->getMessage());
        }
    }

    /**
     * 从回调数据处理支付成功
     */
    private function handlePaySuccessFromNotify(array $data, int $channel): void
    {
        $transactionNo = $data['out_trade_no'] ?? '';
        $tradeNo = $data['trade_no'] ?? '';

        if (empty($transactionNo)) {
            throw new RuntimeException('回调缺少订单号');
        }

        // 调用已有的处理逻辑
        $this->handlePaySuccess($transactionNo, $tradeNo, $channel, $data);
    }

    /**
     * 查找微信支付渠道（生产优先）
     */
    private function findWechatChannel(): ?object
    {
        return (new PayChannelDep())->getActiveByChannel(PayEnum::CHANNEL_WECHAT);
    }

    /**
     * 查找支付宝渠道（生产优先）
     */
    private function findAlipayChannel(): ?object
    {
        return (new PayChannelDep())->getActiveByChannel(PayEnum::CHANNEL_ALIPAY);
    }

    /**
     * 通过交易流水号反查渠道（支持沙盒/生产自动识别）
     * 如果交易流水中记录了 channel_id，直接用；否则走兜底逻辑
     */
    private function findChannelByTransactionNo(string $transactionNo, int $channelType): ?object
    {
        if (empty($transactionNo)) {
            return null;
        }

        $txnDep = new PayTransactionDep();
        $txn = $txnDep->findByTransactionNo($transactionNo);

        if ($txn && $txn->channel_id) {
            $channelDep = new PayChannelDep();
            $channel = $channelDep->findActive($txn->channel_id);
            if ($channel && $channel->channel === $channelType) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * 处理支付成功回调（内部调用，由各渠道验签后调用）
     */
    public function handlePaySuccess(string $transactionNo, string $tradeNo, int $channel, array $rawData): array
    {
        $lockKey = "pay_notify_{$transactionNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        self::throwIf(!$lockVal, '正在处理中');

        try {
            return $this->withTransaction(function () use ($transactionNo, $tradeNo, $channel, $rawData) {
                $txnDep = new PayTransactionDep();
                $txn = $txnDep->findByTransactionNo($transactionNo);

                if (!$txn) {
                    $this->log("支付流水不存在，跳过 transactionNo={$transactionNo}");
                    return self::success();
                }

                // 幂等：已成功则跳过
                if ($txn->status === PayEnum::TXN_SUCCESS) {
                    $this->log("交易已成功，跳过 transactionNo={$transactionNo}");
                    return self::success();
                }

                // 更新交易状态
                $txnDep->updateStatus($txn->id, $txn->status, PayEnum::TXN_SUCCESS, [
                    'trade_no'     => $tradeNo,
                    'trade_status' => 'SUCCESS',
                    'paid_at'      => date('Y-m-d H:i:s'),
                    'raw_notify'   => json_encode($rawData, JSON_UNESCAPED_UNICODE),
                ]);

                // 更新订单
                $orderDep = new OrderDep();
                $order = $orderDep->find($txn->order_id);

                if ($order) {
                    if (
                        (int) $order->pay_status !== PayEnum::PAY_STATUS_PAID
                        && PayEnum::canTransitPayStatus((int) $order->pay_status, PayEnum::PAY_STATUS_PAID)
                    ) {
                        $orderDep->updatePayStatus($order->id, (int) $order->pay_status, PayEnum::PAY_STATUS_PAID, [
                            'pay_time' => date('Y-m-d H:i:s'),
                            'pay_method' => (string) $txn->pay_method,
                            'channel_id' => (int) $txn->channel_id,
                            'success_transaction_id' => (int) $txn->id,
                        ]);
                        $order->pay_status = PayEnum::PAY_STATUS_PAID;
                    } elseif ((int) $order->success_transaction_id === 0) {
                        $orderDep->update($order->id, [
                            'success_transaction_id' => (int) $txn->id,
                            'channel_id' => (int) $txn->channel_id,
                            'pay_method' => (string) $txn->pay_method,
                        ]);
                    }

                    if (PayEnum::canTransitBizStatus((int) $order->biz_status, PayEnum::BIZ_STATUS_PENDING)) {
                        $orderDep->updateBizStatus($order->id, (int) $order->biz_status, PayEnum::BIZ_STATUS_PENDING);
                    }

                    // 创建履约记录并投递队列
                    $payModule = new PayModule();
                    $actionType = match ((int) $order->order_type) {
                        PayEnum::TYPE_RECHARGE => PayEnum::FULFILL_ACTION_RECHARGE,
                        PayEnum::TYPE_CONSUME  => PayEnum::FULFILL_ACTION_CONSUME,
                        PayEnum::TYPE_GOODS    => PayEnum::FULFILL_ACTION_GOODS,
                        default => PayEnum::FULFILL_ACTION_RECHARGE,
                    };
                    $payModule->createFulfillmentAndDispatch(array_merge($order->toArray(), [
                        'success_transaction_id' => (int) $txn->id,
                        'channel_id' => (int) $txn->channel_id,
                        'pay_method' => (string) $txn->pay_method,
                    ]), $actionType);
                }

                return self::success();
            });
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    /** 投递回调日志到 fast 队列 */
    private function dispatchPayNotifyLog(int $channel, int $notifyType, array $data, string $ip): void
    {
        RedisQueue::connection('default')->send('pay_notify_log', [
            'channel'        => $channel,
            'notify_type'    => $notifyType,
            'transaction_no'  => $data['out_trade_no'] ?? $data['transaction_no'] ?? '',
            'refund_no'      => $data['refund_no'] ?? '',
            'trade_no'       => $data['trade_no'] ?? $data['transaction_id'] ?? '',
            'headers'        => [],
            'raw_data'       => $data,
            'process_status' => 1,
            'process_msg'    => '',
            'ip'             => $ip,
        ]);
    }

    /** 微信回调响应 */
    private function wechatResponse(bool $success, string $message): array
    {
        if ($success) {
            return ['code' => 'SUCCESS', 'message' => 'OK'];
        }
        return ['code' => 'FAIL', 'message' => $message];
    }

    /** 支付宝回调响应 */
    private function alipayResponse(bool $success, string $message): array
    {
        if ($success) {
            return ['code' => 'success', 'msg' => $message];
        }
        return ['code' => 'fail', 'msg' => $message];
    }

    private function log(string $msg, array $context = []): void
    {
        Log::info("[PayNotify] {$msg}", $context);
    }
}

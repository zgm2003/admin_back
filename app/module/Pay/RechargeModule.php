<?php

namespace app\module\Pay;

use app\dep\Pay\OrderDep;
use app\dep\Pay\OrderItemDep;
use app\dep\Pay\PayChannelDep;
use app\dep\Pay\PayTransactionDep;
use app\enum\PayEnum;
use app\module\BaseModule;
use app\service\Common\RedisLock;
use app\service\Pay\OrderNoGenerator;
use app\service\Pay\PayChannelService;
use app\service\Pay\PayDomainService;
use app\validate\Pay\OrderValidate;
use RuntimeException;
use support\Request;

class RechargeModule extends BaseModule
{
    public function myOrders(Request $request): array
    {
        $userId = (int) $request->userId;
        $param = $this->validate($request, OrderValidate::list());
        $param['user_id'] = $userId;
        $param['order_type'] = PayEnum::TYPE_RECHARGE;
        $param['page'] = (int) ($param['page'] ?? 1);
        $param['page_size'] = (int) ($param['page_size'] ?? 10);

        $res = $this->dep(OrderDep::class)->list($param);
        $channelIds = $res->pluck('channel_id')
            ->filter(fn($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
        $orderIds = $res->pluck('id')
            ->filter(fn($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        $channelMap = $this->dep(PayChannelDep::class)->getMapActive($channelIds, ['id', 'name']);
        $transactionMap = $this->dep(PayTransactionDep::class)->getLatestSummaryMapByOrderIds($orderIds);

        $list = $res->map(function ($item) use ($channelMap, $transactionMap) {
            $transaction = $transactionMap[(int) $item->id] ?? null;
            $channel = $channelMap->get((int) $item->channel_id);

            return [
                'id' => $item->id,
                'order_no' => $item->order_no,
                'title' => $item->title,
                'pay_amount' => $item->pay_amount,
                'pay_status' => $item->pay_status,
                'pay_status_text' => PayEnum::$payStatusArr[$item->pay_status] ?? '',
                'biz_status' => $item->biz_status,
                'biz_status_text' => PayEnum::$bizStatusArr[$item->biz_status] ?? '',
                'pay_time' => $item->pay_time,
                'created_at' => $item->created_at,
                'expire_time' => $item->expire_time,
                'channel_id' => $item->channel_id,
                'channel_name' => $channel?->name ?? '',
                'pay_method' => $item->pay_method,
                'pay_method_text' => PayEnum::$methodArr[$item->pay_method] ?? $item->pay_method,
                'transaction_no' => $transaction['transaction_no'] ?? null,
                'transaction_status' => $transaction['transaction_status'] ?? null,
            ];
        });

        $page = [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    public function recharge(Request $request): array
    {
        $userId = (int) $request->userId;
        $body = $request->all();
        $amount = (int) ($body['amount'] ?? 0);
        $payMethod = (string) ($body['pay_method'] ?? '');
        $channelId = (int) ($body['channel_id'] ?? 0);
        $channelType = (int) ($body['channel'] ?? 0);
        $ip = $request->getRealIp();

        self::throwUnless($amount >= 1, '充值金额最小1分');
        self::throwUnless(isset(PayEnum::$methodArr[$payMethod]), '不支持的支付方式');

        $channelService = $this->svc(PayChannelService::class);
        $channel = $channelService->resolveRechargeChannel($channelId, $channelType);
        self::throwUnless($channel, '支付渠道不可用');
        self::throwUnless($channelService->isPayMethodSupportedByChannel($channel, $payMethod), '该渠道未配置当前支付方式');

        $lockKey = "pay_create_order_{$userId}";
        $lockVal = RedisLock::lock($lockKey, 10);
        self::throwIf(!$lockVal, '请勿重复提交');

        try {
            $ongoingOrder = $this->dep(OrderDep::class)->findLatestOngoingRechargeByUser($userId);
            if ($ongoingOrder) {
                $isExpired = !empty($ongoingOrder->expire_time) && strtotime((string) $ongoingOrder->expire_time) <= time();
                if ($isExpired) {
                    $result = $this->svc(PayDomainService::class)->syncPaidOrCloseOrder(
                        (string) $ongoingOrder->order_no,
                        '订单超时自动关闭',
                        'inline_expire_check'
                    );

                    if ($result === 'deferred') {
                        throw new RuntimeException('上一笔订单状态确认中，请稍后再试');
                    }

                    $ongoingOrder = $this->dep(OrderDep::class)->find((int) $ongoingOrder->id);
                    if ($ongoingOrder && (int) $ongoingOrder->pay_status !== PayEnum::PAY_STATUS_CLOSED) {
                        if ((int) $ongoingOrder->pay_status === PayEnum::PAY_STATUS_PAID) {
                            throw new RuntimeException('上一笔充值订单已支付，请刷新查看结果');
                        }

                        throw new RuntimeException('请稍后再试，上一笔充值订单仍在处理中');
                    }
                } else {
                    throw new RuntimeException('请先完成或取消当前未支付的充值订单');
                }
            }

            return $this->withTransaction(function () use ($userId, $amount, $payMethod, $channel, $ip) {
                $blockingOrder = $this->dep(OrderDep::class)->findLatestOngoingRechargeByUser($userId);
                if ($blockingOrder) {
                    throw new RuntimeException('请先完成或取消当前未支付的充值订单');
                }

                $orderNo = OrderNoGenerator::recharge();
                $expireTime = date('Y-m-d H:i:s', time() + PayEnum::ORDER_EXPIRE_SECONDS);

                $orderId = $this->dep(OrderDep::class)->add([
                    'order_no' => $orderNo,
                    'user_id' => $userId,
                    'order_type' => PayEnum::TYPE_RECHARGE,
                    'biz_type' => 'recharge',
                    'title' => '钱包充值 ' . ($amount / 100) . ' 元',
                    'item_count' => 1,
                    'total_amount' => $amount,
                    'discount_amount' => 0,
                    'pay_amount' => $amount,
                    'pay_status' => PayEnum::PAY_STATUS_PENDING,
                    'biz_status' => PayEnum::BIZ_STATUS_INIT,
                    'channel_id' => $channel->id,
                    'pay_method' => $payMethod,
                    'expire_time' => $expireTime,
                    'ip' => $ip,
                ]);

                $this->dep(OrderItemDep::class)->add([
                    'order_id' => $orderId,
                    'item_type' => 'recharge',
                    'title' => '钱包充值 ' . ($amount / 100) . ' 元',
                    'price' => $amount,
                    'quantity' => 1,
                    'amount' => $amount,
                ]);

                return self::success([
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'pay_amount' => $amount,
                    'expire_time' => $expireTime,
                ]);
            });
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    public function createPay(Request $request): array
    {
        $userId = (int) $request->userId;
        $param = $this->validate($request, OrderValidate::createPay());
        $orderNo = (string) ($param['order_no'] ?? '');
        $returnUrl = $this->normalizeReturnUrl($request, (string) ($param['return_url'] ?? ''));
        $ip = $request->getRealIp();

        $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);
        self::throwUnless($order, '订单不存在');
        self::throwUnless((int) $order->user_id === $userId, '无权操作该订单');
        self::throwUnless(
            in_array((int) $order->pay_status, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING], true),
            '订单状态不允许发起支付'
        );

        $payMethod = (string) ($param['pay_method'] ?? ($order->pay_method ?: ''));
        self::throwUnless(isset(PayEnum::$methodArr[$payMethod]), '不支持的支付方式');

        $lockKey = "pay_create_txn_{$orderNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        self::throwIf(!$lockVal, '正在处理中，请稍后');

        try {
            return $this->withTransaction(function () use ($request, $order, $payMethod, $ip, $returnUrl) {
                $channelId = (int) ($order->channel_id ?: 0);
                self::throwIf($channelId <= 0, '未指定支付渠道');

                $channel = $this->dep(PayChannelDep::class)->findActive($channelId);
                self::throwUnless($channel, '支付渠道不可用');

                $channelService = $this->svc(PayChannelService::class);
                self::throwUnless($channelService->isPayMethodSupportedByChannel($channel, $payMethod), '该渠道未配置当前支付方式');

                $lastTxn = $this->dep(PayTransactionDep::class)->findLastActive((int) $order->id);
                if ($lastTxn) {
                    $this->dep(PayTransactionDep::class)->update((int) $lastTxn->id, [
                        'status' => PayEnum::TXN_CLOSED,
                        'closed_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->svc(PayDomainService::class)->closeThirdPartyPaymentSafely(
                        (int) $lastTxn->channel_id,
                        (int) $lastTxn->channel,
                        (string) $lastTxn->transaction_no
                    );
                }

                $txnId = $this->dep(PayTransactionDep::class)->add([
                    'transaction_no' => OrderNoGenerator::transaction(),
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'attempt_no' => $lastTxn ? ((int) $lastTxn->attempt_no + 1) : 1,
                    'channel_id' => $channel->id,
                    'channel' => $channel->channel,
                    'pay_method' => $payMethod,
                    'amount' => $order->pay_amount,
                    'status' => PayEnum::TXN_CREATED,
                ]);

                $transaction = $this->dep(PayTransactionDep::class)->find((int) $txnId);
                $payResponse = $channelService->dispatchPayRequest(
                    (int) $channel->channel,
                    (int) $channel->id,
                    $payMethod,
                    $this->buildPayPayload(
                        (int) $channel->channel,
                        $payMethod,
                        (string) $transaction->transaction_no,
                        $order,
                        $request,
                        $ip,
                        $returnUrl
                    )
                );

                $payResponseBundle = $channelService->preparePayResponse($payResponse);
                $payData = $payResponseBundle['client'];

                $this->dep(PayTransactionDep::class)->update((int) $txnId, [
                    'status' => PayEnum::TXN_WAITING,
                    'channel_resp' => json_encode($payResponseBundle['raw'], JSON_UNESCAPED_UNICODE),
                ]);

                $this->dep(OrderDep::class)->updatePayStatus(
                    (int) $order->id,
                    (int) $order->pay_status,
                    PayEnum::PAY_STATUS_PAYING,
                    ['pay_method' => $payMethod]
                );

                return self::success([
                    'transaction_no' => (string) $transaction->transaction_no,
                    'txn_id' => (int) $txnId,
                    'order_no' => $order->order_no,
                    'pay_amount' => $order->pay_amount,
                    'channel' => $channel->channel,
                    'pay_method' => $payMethod,
                    'notify_url' => $channel->notify_url,
                    'return_url' => $returnUrl,
                    'pay_data' => $payData,
                ]);
            });
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    public function cancelOrder(Request $request): array
    {
        $userId = (int) $request->userId;
        $param = $this->validate($request, OrderValidate::cancelOrder());
        $order = $this->dep(OrderDep::class)->findByOrderNo($param['order_no']);

        self::throwUnless($order, '订单不存在');
        self::throwUnless((int) $order->user_id === $userId, '无权操作该订单');
        self::throwIf(
            !in_array((int) $order->pay_status, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING], true),
            '该订单状态不允许取消'
        );

        $result = $this->svc(PayDomainService::class)->syncPaidOrCloseOrder(
            (string) $order->order_no,
            $param['reason'] ?? '用户取消订单',
            'user_cancel'
        );

        self::throwIf($result === 'deferred', '第三方状态确认失败，请稍后重试');

        return self::success();
    }

    public function queryResult(Request $request): array
    {
        $userId = (int) $request->userId;
        $orderNo = (string) ($request->all()['order_no'] ?? '');

        $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);
        self::throwUnless($order, '订单不存在');
        self::throwUnless((int) $order->user_id === $userId, '无权查看该订单');

        $txn = $this->dep(PayTransactionDep::class)->findLastActive((int) $order->id);
        if (!$txn && $order->success_transaction_id) {
            $txn = $this->dep(PayTransactionDep::class)->find((int) $order->success_transaction_id);
        }

        return self::success([
            'order_no' => $order->order_no,
            'pay_status' => $order->pay_status,
            'biz_status' => $order->biz_status,
            'pay_time' => $order->pay_time,
            'transaction' => $txn ? [
                'transaction_no' => $txn->transaction_no,
                'status' => $txn->status,
                'trade_no' => $txn->trade_no,
            ] : null,
        ]);
    }

    public function orderDetail(Request $request): array
    {
        $userId = (int) $request->userId;
        $orderNo = (string) ($request->all()['order_no'] ?? '');

        $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);
        self::throwUnless($order, '订单不存在');
        self::throwUnless((int) $order->user_id === $userId, '无权查看该订单');

        $items = $this->dep(OrderItemDep::class)->getByOrderId((int) $order->id);

        return self::success([
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'order_type' => $order->order_type,
                'title' => $order->title,
                'total_amount' => $order->total_amount,
                'pay_amount' => $order->pay_amount,
                'pay_status' => $order->pay_status,
                'biz_status' => $order->biz_status,
                'pay_time' => $order->pay_time,
                'expire_time' => $order->expire_time,
                'created_at' => $order->created_at,
            ],
            'items' => $items->map(fn($item) => [
                'title' => $item->title,
                'price' => $item->price,
                'amount' => $item->amount,
            ]),
        ]);
    }

    private function buildPayPayload(
        int $channel,
        string $payMethod,
        string $transactionNo,
        object $order,
        Request $request,
        string $ip,
        string $returnUrl = ''
    ): array {
        $title = trim((string) ($order->title ?? '')) ?: "订单支付 {$order->order_no}";
        $title = mb_substr($title, 0, 64);
        $body = $request->all();

        if ($channel === PayEnum::CHANNEL_WECHAT) {
            $payload = [
                'out_trade_no' => $transactionNo,
                'description' => $title,
                'amount' => [
                    'total' => (int) $order->pay_amount,
                    'currency' => 'CNY',
                ],
            ];

            if ($payMethod === PayEnum::METHOD_H5) {
                $payload['scene_info'] = [
                    'payer_client_ip' => $ip ?: '127.0.0.1',
                    'h5_info' => ['type' => 'Wap'],
                ];
            }

            if (in_array($payMethod, [PayEnum::METHOD_MP, PayEnum::METHOD_MINI], true)) {
                $openid = (string) ($body['openid'] ?? '');
                self::throwUnless($openid, '缺少 openid');
                $payload['payer'] = ['openid' => $openid];
            }

            return $payload;
        }

        $payload = [
            'out_trade_no' => $transactionNo,
            'total_amount' => number_format(((int) $order->pay_amount) / 100, 2, '.', ''),
            'subject' => mb_substr($title, 0, 128),
        ];

        if ($returnUrl !== '') {
            $payload['_return_url'] = $returnUrl;
        }

        return $payload;
    }

    private function normalizeReturnUrl(Request $request, string $returnUrl): string
    {
        $returnUrl = trim($returnUrl);
        if ($returnUrl === '') {
            return '';
        }

        $parts = parse_url($returnUrl);
        self::throwUnless(is_array($parts), '非法回跳地址');

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        self::throwUnless(in_array($scheme, ['http', 'https'], true), '非法回跳地址');
        self::throwUnless($host !== '', '非法回跳地址');

        $allowedHosts = [];
        foreach ([
            $request->host(true),
            parse_url((string) $request->header('origin', ''), PHP_URL_HOST),
            parse_url((string) $request->header('referer', ''), PHP_URL_HOST),
        ] as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate === '') {
                continue;
            }

            $allowedHosts[$candidate] = true;
            $trimmed = preg_replace('/^www\./', '', $candidate) ?: $candidate;
            $allowedHosts[$trimmed] = true;

            if (str_contains($trimmed, '.')) {
                $allowedHosts['www.' . $trimmed] = true;
            }
        }

        self::throwUnless(isset($allowedHosts[$host]), '非法回跳地址');

        return $returnUrl;
    }
}

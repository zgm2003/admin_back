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
    /** 用户充值订单列表 */
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

    /** 创建充值订单 */
    public function recharge(Request $request): array
    {
        $userId = (int) $request->userId;
        $param = $this->validate($request, OrderValidate::recharge());
        $amount = (int) $param['amount'];
        $payMethod = (string) $param['pay_method'];
        $ip = $request->getRealIp();
        $channel = $this->resolveRechargeChannelOrFail((int) $param['channel_id'], $payMethod);

        $lockKey = "pay_create_order_{$userId}";
        $lockVal = RedisLock::lock($lockKey, 10);
        self::throwIf(!$lockVal, '请勿重复提交');

        try {
            $this->assertRechargeCreationAllowed($userId);

            return $this->createRechargeOrder($userId, $amount, $payMethod, $channel, $ip);
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    /** 基于订单发起支付 */
    public function createPay(Request $request): array
    {
        $userId = (int) $request->userId;
        $param = $this->validate($request, OrderValidate::createPay());
        $orderNo = (string) ($param['order_no'] ?? '');
        $returnUrl = $this->normalizeReturnUrl($request, (string) ($param['return_url'] ?? ''));
        $ip = $request->getRealIp();
        $order = $this->resolveCreatePayOrderOrFail($orderNo, $userId);
        $payMethod = $this->resolveCreatePayMethodOrFail($param, $order);

        $lockKey = "pay_create_txn_{$orderNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        self::throwIf(!$lockVal, '正在处理中，请稍后');

        try {
            return $this->createPayAttempt($request, $order, $payMethod, $ip, $returnUrl);
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    /** 用户主动取消订单 */
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

    /** 查询订单支付结果 */
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

    /** 查询订单详情 */
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

    /** 构建第三方支付请求参数 */
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

    /** 校验并规范化支付回跳地址 */
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

    /** 按渠道 ID 解析并校验本次充值使用的渠道配置 */
    private function resolveRechargeChannelOrFail(int $channelId, string $payMethod): object
    {
        self::throwIf($channelId <= 0, '未指定支付渠道');

        $channel = $this->dep(PayChannelDep::class)->findActive($channelId);
        $channelService = $this->svc(PayChannelService::class);

        self::throwUnless($channel, '支付渠道不可用');
        self::throwUnless($channelService->isPayMethodSupportedByChannel($channel, $payMethod), '该渠道未配置当前支付方式');

        return $channel;
    }

    /** 检查用户是否存在未收口的充值订单 */
    private function assertRechargeCreationAllowed(int $userId): void
    {
        $ongoingOrder = $this->dep(OrderDep::class)->findLatestOngoingRechargeByUser($userId);
        if (!$ongoingOrder) {
            return;
        }

        if (!$this->isOrderExpired((string) ($ongoingOrder->expire_time ?? ''))) {
            throw new RuntimeException('请先完成或取消当前未支付的充值订单');
        }

        $freshOrder = $this->syncExpiredRechargeOrder($ongoingOrder);
        $this->assertExpiredOrderClosed($freshOrder);
    }

    /** 在事务内创建充值订单与订单项 */
    private function createRechargeOrder(int $userId, int $amount, string $payMethod, object $channel, string $ip): array
    {
        return $this->withTransaction(function () use ($userId, $amount, $payMethod, $channel, $ip) {
            $this->assertNoBlockingRechargeOrder($userId);

            $orderNo = OrderNoGenerator::recharge();
            $expireTime = date('Y-m-d H:i:s', time() + PayEnum::ORDER_EXPIRE_SECONDS);
            $title = $this->buildRechargeTitle($amount);

            $orderId = $this->dep(OrderDep::class)->add([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'order_type' => PayEnum::TYPE_RECHARGE,
                'biz_type' => 'recharge',
                'title' => $title,
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
                'title' => $title,
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
    }

    /** 事务内二次兜底，防止并发下重复建单 */
    private function assertNoBlockingRechargeOrder(int $userId): void
    {
        $blockingOrder = $this->dep(OrderDep::class)->findLatestOngoingRechargeByUser($userId);
        if ($blockingOrder) {
            throw new RuntimeException('请先完成或取消当前未支付的充值订单');
        }
    }

    /** 对已过期充值单执行补偿查单或关单收口 */
    private function syncExpiredRechargeOrder(object $order): ?object
    {
        $result = $this->svc(PayDomainService::class)->syncPaidOrCloseOrder(
            (string) $order->order_no,
            '订单超时自动关闭',
            'inline_expire_check'
        );

        if ($result === 'deferred') {
            throw new RuntimeException('上一笔订单状态确认中，请稍后再试');
        }

        return $this->dep(OrderDep::class)->find((int) $order->id);
    }

    /** 过期订单收口后，仅允许 closed 继续进入建单流程 */
    private function assertExpiredOrderClosed(?object $order): void
    {
        if (!$order || (int) $order->pay_status === PayEnum::PAY_STATUS_CLOSED) {
            return;
        }

        if ((int) $order->pay_status === PayEnum::PAY_STATUS_PAID) {
            throw new RuntimeException('上一笔充值订单已支付，请刷新查看结果');
        }

        throw new RuntimeException('请稍后再试，上一笔充值订单仍在处理中');
    }

    /** 判断订单是否已过期 */
    private function isOrderExpired(string $expireTime): bool
    {
        return $expireTime !== '' && strtotime($expireTime) <= time();
    }

    /** 统一生成充值订单标题 */
    private function buildRechargeTitle(int $amount): string
    {
        return '钱包充值 ' . ($amount / 100) . ' 元';
    }

    /** 校验订单归属与状态，确认允许继续发起支付 */
    private function resolveCreatePayOrderOrFail(string $orderNo, int $userId): object
    {
        $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);

        self::throwUnless($order, '订单不存在');
        self::throwUnless((int) $order->user_id === $userId, '无权操作该订单');
        self::throwUnless(
            in_array((int) $order->pay_status, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING], true),
            '订单状态不允许发起支付'
        );

        return $order;
    }

    /** 规范化本次发起支付使用的方式 */
    private function resolveCreatePayMethodOrFail(array $param, object $order): string
    {
        $payMethod = (string) ($param['pay_method'] ?? ($order->pay_method ?: ''));
        self::throwUnless(isset(PayEnum::$methodArr[$payMethod]), '不支持的支付方式');

        return $payMethod;
    }

    /** 在事务内创建支付尝试并返回前端所需支付数据 */
    private function createPayAttempt(Request $request, object $order, string $payMethod, string $ip, string $returnUrl): array
    {
        return $this->withTransaction(function () use ($request, $order, $payMethod, $ip, $returnUrl) {
            $channel = $this->resolveOrderChannelForPayOrFail($order, $payMethod);
            $lastTxn = $this->closeLastActiveTransactionIfExists((int) $order->id);
            $transaction = $this->createPendingPayTransaction($order, $channel, $payMethod, $lastTxn);
            $payResponseBundle = $this->requestPayResponseBundle($request, $order, $channel, $payMethod, $transaction['transaction_no'], $ip, $returnUrl);

            $this->persistCreatePayState($order, $payMethod, (int) $transaction['id'], $payResponseBundle);

            return self::success([
                'transaction_no' => $transaction['transaction_no'],
                'txn_id' => (int) $transaction['id'],
                'order_no' => $order->order_no,
                'pay_amount' => $order->pay_amount,
                'channel' => $channel->channel,
                'pay_method' => $payMethod,
                'notify_url' => $channel->notify_url,
                'return_url' => $returnUrl,
                'pay_data' => $payResponseBundle['client'],
            ]);
        });
    }

    /** 解析订单绑定的支付渠道并校验支付方式是否可用 */
    private function resolveOrderChannelForPayOrFail(object $order, string $payMethod): object
    {
        $channelId = (int) ($order->channel_id ?: 0);
        self::throwIf($channelId <= 0, '未指定支付渠道');

        $channel = $this->dep(PayChannelDep::class)->findActive($channelId);
        self::throwUnless($channel, '支付渠道不可用');

        $channelService = $this->svc(PayChannelService::class);
        self::throwUnless($channelService->isPayMethodSupportedByChannel($channel, $payMethod), '该渠道未配置当前支付方式');

        return $channel;
    }

    /** 收口上一笔未完成支付尝试，并同步尝试第三方关单 */
    private function closeLastActiveTransactionIfExists(int $orderId): ?object
    {
        $lastTxn = $this->dep(PayTransactionDep::class)->findLastActive($orderId);
        if (!$lastTxn) {
            return null;
        }

        $this->dep(PayTransactionDep::class)->update((int) $lastTxn->id, [
            'status' => PayEnum::TXN_CLOSED,
            'closed_at' => date('Y-m-d H:i:s'),
        ]);
        $this->svc(PayDomainService::class)->closeThirdPartyPaymentSafely(
            (int) $lastTxn->channel_id,
            (int) $lastTxn->channel,
            (string) $lastTxn->transaction_no
        );

        return $lastTxn;
    }

    /** 创建新的待支付流水 */
    private function createPendingPayTransaction(object $order, object $channel, string $payMethod, ?object $lastTxn): array
    {
        $transactionNo = OrderNoGenerator::transaction();
        $txnId = $this->dep(PayTransactionDep::class)->add([
            'transaction_no' => $transactionNo,
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'attempt_no' => $lastTxn ? ((int) $lastTxn->attempt_no + 1) : 1,
            'channel_id' => $channel->id,
            'channel' => $channel->channel,
            'pay_method' => $payMethod,
            'amount' => $order->pay_amount,
            'status' => PayEnum::TXN_CREATED,
        ]);

        return [
            'id' => (int) $txnId,
            'transaction_no' => $transactionNo,
        ];
    }

    /** 调用第三方支付网关并规范化响应结构 */
    private function requestPayResponseBundle(
        Request $request,
        object $order,
        object $channel,
        string $payMethod,
        string $transactionNo,
        string $ip,
        string $returnUrl
    ): array {
        $channelService = $this->svc(PayChannelService::class);
        $payResponse = $channelService->dispatchPayRequest(
            (int) $channel->channel,
            (int) $channel->id,
            $payMethod,
            $this->buildPayPayload(
                (int) $channel->channel,
                $payMethod,
                $transactionNo,
                $order,
                $request,
                $ip,
                $returnUrl
            )
        );

        return $channelService->preparePayResponse($payResponse);
    }

    /** 落库支付响应并推进订单到支付中状态 */
    private function persistCreatePayState(object $order, string $payMethod, int $txnId, array $payResponseBundle): void
    {
        $this->dep(PayTransactionDep::class)->update($txnId, [
            'status' => PayEnum::TXN_WAITING,
            'channel_resp' => json_encode($payResponseBundle['raw'], JSON_UNESCAPED_UNICODE),
        ]);

        $this->dep(OrderDep::class)->updatePayStatus(
            (int) $order->id,
            (int) $order->pay_status,
            PayEnum::PAY_STATUS_PAYING,
            ['pay_method' => $payMethod]
        );
    }
}

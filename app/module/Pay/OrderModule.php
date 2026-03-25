<?php

namespace app\module\Pay;

use app\dep\Pay\OrderDep;
use app\dep\Pay\OrderItemDep;
use app\dep\Pay\PayTransactionDep;
use app\dep\Pay\OrderFulfillmentDep;
use app\dep\Pay\UserWalletDep;
use app\dep\Pay\WalletTransactionDep;
use app\dep\Pay\PayChannelDep;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\service\Common\RedisLock;
use app\service\Pay\OrderNoGenerator;
use app\service\Pay\WalletService;
use app\validate\Pay\OrderValidate;
use RuntimeException;
use support\Db;
use support\Request;
use Webman\RedisQueue\Client as RedisQueue;
use Yansongda\Supports\Collection;

/**
 * 统一订单模块
 * 负责：充值下单、发起支付、支付查询、订单关闭、备注
 */
class OrderModule extends BaseModule
{
    // ==================== 公开接口 ====================

    /** 初始化 */
    public function init($request): array
    {
        $dict = $this->svc(DictService::class)
            ->setCommonStatusArr()
            ->setPayChannelArr()
            ->setPayMethodArr()
            ->setOrderTypeArr()
            ->getDict();

        $dict['recharge_preset_arr'] = PayEnum::$rechargePresetArr;
        $dict['pay_status_arr'] = DictService::enumToDict(PayEnum::$payStatusArr);
        $dict['biz_status_arr'] = DictService::enumToDict(PayEnum::$bizStatusArr);
        $dict['refund_status_arr'] = DictService::enumToDict(PayEnum::$refundStatusArr);

        return self::success(['dict' => $dict]);
    }

    /** 列表 */
    public function list($request): array
    {
        $param = $this->validate($request, OrderValidate::list());
        $res = $this->dep(OrderDep::class)->list($param);

        $list = $res->map(function ($item) {
            return [
                'id'              => $item->id,
                'order_no'        => $item->order_no,
                'user_id'         => $item->user_id,
                'order_type'      => $item->order_type,
                'order_type_text' => PayEnum::$orderTypeArr[$item->order_type] ?? '',
                'title'           => $item->title,
                'total_amount'    => $item->total_amount,
                'discount_amount' => $item->discount_amount,
                'pay_amount'      => $item->pay_amount,
                'refunded_amount' => $item->refunded_amount,
                'pay_status'      => $item->pay_status,
                'pay_status_text' => PayEnum::$payStatusArr[$item->pay_status] ?? '',
                'biz_status'      => $item->biz_status,
                'biz_status_text' => PayEnum::$bizStatusArr[$item->biz_status] ?? '',
                'refund_status'   => $item->refund_status,
                'refund_status_text' => PayEnum::$refundStatusArr[$item->refund_status] ?? '',
                'pay_time'        => $item->pay_time,
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
        $param = $this->validate($request, OrderValidate::detail());
        $order = $this->dep(OrderDep::class)->getOrFail($param['id']);

        $items = $this->dep(OrderItemDep::class)->getByOrderId($order->id);
        $itemsData = $items->map(fn($item) => [
            'id'       => $item->id,
            'title'    => $item->title,
            'price'    => $item->price,
            'quantity' => $item->quantity,
            'amount'   => $item->amount,
        ]);

        $channel = null;
        if ($order->channel_id) {
            $ch = $this->dep(PayChannelDep::class)->find($order->channel_id);
            if ($ch) {
                $channel = ['id' => $ch->id, 'name' => $ch->name, 'channel' => $ch->channel];
            }
        }

        return self::success([
            'order' => [
                'id'              => $order->id,
                'order_no'        => $order->order_no,
                'user_id'         => $order->user_id,
                'order_type'      => $order->order_type,
                'biz_type'        => $order->biz_type,
                'biz_id'          => $order->biz_id,
                'title'           => $order->title,
                'total_amount'    => $order->total_amount,
                'discount_amount' => $order->discount_amount,
                'pay_amount'      => $order->pay_amount,
                'refunded_amount' => $order->refunded_amount,
                'pay_status'      => $order->pay_status,
                'pay_status_text' => PayEnum::$payStatusArr[$order->pay_status] ?? '',
                'biz_status'      => $order->biz_status,
                'biz_status_text' => PayEnum::$bizStatusArr[$order->biz_status] ?? '',
                'refund_status'   => $order->refund_status,
                'refund_status_text' => PayEnum::$refundStatusArr[$order->refund_status] ?? '',
                'pay_time'        => $order->pay_time,
                'expire_time'     => $order->expire_time,
                'close_time'      => $order->close_time,
                'close_reason'    => $order->close_reason,
                'biz_done_at'     => $order->biz_done_at,
                'remark'          => $order->remark,
                'admin_remark'    => $order->admin_remark,
                'channel'         => $channel,
                'pay_method'      => $order->pay_method,
                'extra'           => $order->extra ? json_decode($order->extra, true) : [],
                'created_at'      => $order->created_at,
            ],
            'items' => $itemsData,
        ]);
    }

    /** 状态统计 */
    public function statusCount($request): array
    {
        $counts = $this->dep(OrderDep::class)->countByStatus();
        $result = [];
        foreach (PayEnum::$payStatusArr as $k => $v) {
            $result[$k] = ['label' => $v, 'count' => $counts[$k] ?? 0];
        }
        return self::success(['counts' => $result]);
    }

    /** 关闭订单 */
    public function close($request): array
    {
        $param = $this->validate($request, OrderValidate::close());
        $order = $this->dep(OrderDep::class)->getOrFail($param['id']);

        self::throwIf(
            !in_array($order->pay_status, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING]),
            '该订单状态不允许关闭'
        );

        $lockKey = "pay_create_txn_{$order->order_no}";
        $lockVal = RedisLock::lock($lockKey, 10);
        self::throwIf(!$lockVal, '操作过于频繁，请稍后重试');

        try {
            $this->dep(OrderDep::class)->closeOrder($order->id, $order->pay_status, $param['reason'] ?? '管理员关闭');
            return self::success();
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    /** 备注 */
    public function remark($request): array
    {
        $param = $this->validate($request, OrderValidate::remark());
        $this->dep(OrderDep::class)->getOrFail($param['id']);
        $this->dep(OrderDep::class)->update($param['id'], ['admin_remark' => $param['remark'] ?? '']);
        return self::success();
    }

    // ==================== App 端接口（接受 Request）====================

    /** 充值下单（App端） */
    public function recharge(Request $request): array
    {
        $userId = (int) ($request->user_id ?? 0);
        $body = $request->all();
        $amount = (int) ($body['amount'] ?? 0);
        $payMethod = $body['pay_method'] ?? '';
        $channelId = (int) ($body['channel_id'] ?? 0);
        $ip = $request->getRealIp();

        self::throwUnless($amount >= 1, '充值金额最小1分');
        self::throwUnless(in_array($payMethod, array_keys(PayEnum::$methodArr)), '不支持的支付方式');

        $channel = $this->dep(PayChannelDep::class)->findActive($channelId);
        self::throwUnless($channel, '支付渠道不可用');

        $lockKey = "pay_create_order_{$userId}";
        $lockVal = RedisLock::lock($lockKey, 10);
        self::throwIf(!$lockVal, '请勿重复提交');

        try {
            return $this->withTransaction(function () use ($userId, $amount, $payMethod, $channel, $ip) {
                $orderNo = OrderNoGenerator::recharge();
                $expireTime = date('Y-m-d H:i:s', time() + PayEnum::ORDER_EXPIRE_SECONDS);

                $orderId = $this->dep(OrderDep::class)->add([
                    'order_no'      => $orderNo,
                    'user_id'       => $userId,
                    'order_type'    => PayEnum::TYPE_RECHARGE,
                    'biz_type'      => 'recharge',
                    'title'         => '钱包充值 ' . ($amount / 100) . ' 元',
                    'item_count'    => 1,
                    'total_amount'  => $amount,
                    'discount_amount' => 0,
                    'pay_amount'    => $amount,
                    'pay_status'    => PayEnum::PAY_STATUS_PENDING,
                    'biz_status'    => PayEnum::BIZ_STATUS_INIT,
                    'refund_status' => PayEnum::REFUND_STATUS_NONE,
                    'channel_id'    => $channel->id,
                    'pay_method'   => $payMethod,
                    'expire_time'   => $expireTime,
                    'ip'            => $ip,
                ]);

                $this->dep(OrderItemDep::class)->add([
                    'order_id'  => $orderId,
                    'item_type' => 'recharge',
                    'title'     => '钱包充值 ' . ($amount / 100) . ' 元',
                    'price'     => $amount,
                    'quantity'  => 1,
                    'amount'    => $amount,
                ]);

                return self::success([
                    'order_id'  => $orderId,
                    'order_no'  => $orderNo,
                    'pay_amount' => $amount,
                    'expire_time' => $expireTime,
                ]);
            });
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
    }

    /** 发起支付（App端） */
    public function createPay(Request $request): array
    {
        $userId = (int) ($request->user_id ?? 0);
        $body = $request->all();
        $orderNo = $body['order_no'] ?? '';
        $ip = $request->getRealIp();

        $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);
        self::throwUnless($order, '订单不存在');
        self::throwUnless($order->user_id === $userId, '无权操作该订单');
        self::throwUnless(in_array($order->pay_status, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING]), '订单状态不允许发起支付');

        $payMethod = $body['pay_method'] ?? ($order->pay_method ?: '');
        self::throwUnless(in_array($payMethod, array_keys(PayEnum::$methodArr), true), '不支持的支付方式');

        $lockKey = "pay_create_txn_{$orderNo}";
        $lockVal = RedisLock::lock($lockKey, 30);
        self::throwIf(!$lockVal, '正在处理中，请稍后');

        try {
            return $this->withTransaction(function () use ($request, $order, $payMethod, $ip) {
                $channelId = $order->channel_id ?: 0;
                self::throwIf(!$channelId, '未指定支付渠道');

                $channel = $this->dep(PayChannelDep::class)->findActive($channelId);
                self::throwUnless($channel, '支付渠道不可用');

                $lastTxn = $this->dep(PayTransactionDep::class)->findLastActive($order->id);
                if ($lastTxn) {
                    $this->dep(PayTransactionDep::class)->update($lastTxn->id, [
                        'status' => PayEnum::TXN_CLOSED,
                        'closed_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                $attemptNo = $lastTxn ? $lastTxn->attempt_no + 1 : 1;
                $transactionNo = OrderNoGenerator::transaction();

                $txnId = $this->dep(PayTransactionDep::class)->add([
                    'transaction_no' => $transactionNo,
                    'order_id'      => $order->id,
                    'order_no'      => $order->order_no,
                    'attempt_no'    => $attemptNo,
                    'channel_id'    => $channel->id,
                    'channel'       => $channel->channel,
                    'pay_method'    => $payMethod,
                    'amount'        => $order->pay_amount,
                    'status'        => PayEnum::TXN_CREATED,
                ]);

                $payPayload = $this->buildPayPayload(
                    (int) $channel->channel,
                    $payMethod,
                    $transactionNo,
                    $order,
                    $request,
                    $ip,
                    (string) ($channel->return_url ?? '')
                );
                $payResponse = $this->dispatchPayRequest((int) $channel->channel, (int) $channel->id, $payMethod, $payPayload);
                $payData = $this->normalizePayResponse($payResponse);

                $this->dep(PayTransactionDep::class)->update($txnId, [
                    'status'       => PayEnum::TXN_WAITING,
                    'channel_resp' => json_encode($payData, JSON_UNESCAPED_UNICODE),
                ]);

                $this->dep(OrderDep::class)->updatePayStatus(
                    $order->id,
                    $order->pay_status,
                    PayEnum::PAY_STATUS_PAYING,
                    ['pay_method' => $payMethod]
                );

                return self::success([
                    'transaction_no' => $transactionNo,
                    'txn_id'         => $txnId,
                    'order_no'       => $order->order_no,
                    'pay_amount'     => $order->pay_amount,
                    'channel'        => $channel->channel,
                    'pay_method'     => $payMethod,
                    'notify_url'     => $channel->notify_url,
                    'return_url'     => $channel->return_url,
                    'pay_data'       => $payData,
                ]);
            });
        } finally {
            RedisLock::unlock($lockKey, $lockVal);
        }
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
                'description'  => $title,
                'amount'       => [
                    'total'    => (int) $order->pay_amount,
                    'currency' => 'CNY',
                ],
            ];

            if ($payMethod === PayEnum::METHOD_H5) {
                $payload['scene_info'] = [
                    'payer_client_ip' => $ip ?: '127.0.0.1',
                    'h5_info' => [
                        'type' => 'Wap',
                    ],
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
            'subject'      => mb_substr($title, 0, 128),
        ];

        $quitUrl = (string) ($body['quit_url'] ?? $returnUrl);
        if ($quitUrl !== '') {
            $payload['quit_url'] = $quitUrl;
        }

        return $payload;
    }

    private function dispatchPayRequest(int $channel, int $channelId, string $payMethod, array $payload): mixed
    {
        $sdk = new PaySdk();

        if ($channel === PayEnum::CHANNEL_WECHAT) {
            return match ($payMethod) {
                PayEnum::METHOD_APP => $sdk->wechatApp($channelId, $payload),
                PayEnum::METHOD_H5 => $sdk->wechatH5($channelId, $payload),
                PayEnum::METHOD_MINI => $sdk->wechatMini($channelId, $payload),
                PayEnum::METHOD_MP => $sdk->wechatMp($channelId, $payload),
                PayEnum::METHOD_SCAN, PayEnum::METHOD_WEB => $sdk->wechatScan($channelId, $payload),
                default => throw new RuntimeException('微信支付暂不支持当前支付方式'),
            };
        }

        if ($channel === PayEnum::CHANNEL_ALIPAY) {
            return match ($payMethod) {
                PayEnum::METHOD_APP => $sdk->alipayApp($channelId, $payload),
                PayEnum::METHOD_H5 => $sdk->alipayWap($channelId, $payload),
                PayEnum::METHOD_WEB => $sdk->alipayWeb($channelId, $payload),
                PayEnum::METHOD_SCAN => $sdk->alipayScan($channelId, $payload),
                PayEnum::METHOD_MINI => $sdk->alipayMini($channelId, $payload),
                default => throw new RuntimeException('支付宝暂不支持当前支付方式'),
            };
        }

        throw new RuntimeException('不支持的支付渠道');
    }

    private function normalizePayResponse(mixed $response): array
    {
        if ($response instanceof Collection) {
            return $response->toArray();
        }

        if (is_array($response)) {
            return $response;
        }

        if (is_object($response)) {
            if (method_exists($response, 'toArray')) {
                /** @var callable $toArray */
                $toArray = [$response, 'toArray'];
                return $toArray();
            }

            if (method_exists($response, 'getContent')) {
                /** @var callable $getContent */
                $getContent = [$response, 'getContent'];
                return ['content' => (string) $getContent()];
            }

            return json_decode(json_encode($response, JSON_UNESCAPED_UNICODE), true) ?: ['content' => (string) $response];
        }

        return ['content' => (string) $response];
    }

    /** 支付查询（App端） */
    public function queryResult(Request $request): array
    {
        $body = $request->all();
        $orderNo = $body['order_no'] ?? '';

        $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);
        self::throwUnless($order, '订单不存在');

        $txn = $this->dep(PayTransactionDep::class)->findLastActive($order->id);

        return self::success([
            'order_no'   => $order->order_no,
            'pay_status' => $order->pay_status,
            'biz_status' => $order->biz_status,
            'pay_time'   => $order->pay_time,
            'transaction' => $txn ? [
                'transaction_no' => $txn->transaction_no,
                'status'         => $txn->status,
                'trade_no'      => $txn->trade_no,
            ] : null,
        ]);
    }

    /** 订单详情（App端） */
    public function orderDetail(Request $request): array
    {
        $body = $request->all();
        $orderNo = $body['order_no'] ?? '';

        $order = $this->dep(OrderDep::class)->findByOrderNo($orderNo);
        self::throwUnless($order, '订单不存在');

        $items = $this->dep(OrderItemDep::class)->getByOrderId($order->id);

        return self::success([
            'order' => [
                'id'              => $order->id,
                'order_no'        => $order->order_no,
                'order_type'      => $order->order_type,
                'title'           => $order->title,
                'total_amount'    => $order->total_amount,
                'pay_amount'      => $order->pay_amount,
                'pay_status'      => $order->pay_status,
                'biz_status'      => $order->biz_status,
                'refund_status'   => $order->refund_status,
                'pay_time'        => $order->pay_time,
                'expire_time'     => $order->expire_time,
                'created_at'      => $order->created_at,
            ],
            'items' => $items->map(fn($item) => [
                'title'  => $item->title,
                'price'  => $item->price,
                'amount' => $item->amount,
            ]),
        ]);
    }

    /** 钱包信息（App端） */
    public function walletInfo(Request $request): array
    {
        $userId = (int) ($request->user_id ?? 0);
        $wallet = (new WalletService())->getOrCreateWallet($userId);

        return self::success([
            'balance'        => $wallet['balance'],
            'frozen'         => $wallet['frozen'],
            'total_recharge' => $wallet['total_recharge'],
            'total_consume'  => $wallet['total_consume'],
            'total_refund'   => $wallet['total_refund'],
        ]);
    }

    /** 钱包流水（App端） */
    public function walletBills(Request $request): array
    {
        $userId = (int) ($request->user_id ?? 0);
        $body = $request->all();
        $page = (int) ($body['page'] ?? 1);
        $pageSize = (int) ($body['page_size'] ?? 20);

        $res = $this->dep(WalletTransactionDep::class)->listByUserId($userId, $page, $pageSize);

        $list = $res->map(fn($item) => [
            'id'              => $item->id,
            'biz_action_no'   => $item->biz_action_no,
            'type'            => $item->type,
            'type_text'       => PayEnum::$walletTypeArr[$item->type] ?? '',
            'available_delta' => $item->available_delta,
            'frozen_delta'    => $item->frozen_delta,
            'balance_before'  => $item->balance_before,
            'balance_after'   => $item->balance_after,
            'title'           => $item->title,
            'order_no'        => $item->order_no,
            'created_at'      => $item->created_at,
        ]);

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }
}

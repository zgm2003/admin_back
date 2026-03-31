<?php

namespace app\module\Pay;

use app\dep\Pay\OrderDep;
use app\dep\Pay\OrderItemDep;
use app\dep\Pay\PayChannelDep;
use app\dep\User\UsersDep;
use app\enum\PayEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\service\Pay\PayDomainService;
use app\validate\Pay\OrderValidate;

class OrderAdminModule extends BaseModule
{
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

        return self::success(['dict' => $dict]);
    }

    public function list($request): array
    {
        $param = $this->validate($request, OrderValidate::list());
        $res = $this->dep(OrderDep::class)->list($param);
        $userMap = $this->dep(UsersDep::class)->getMap($res->pluck('user_id')->all(), ['id', 'username', 'email']);

        $list = $res->map(function ($item) use ($userMap) {
            $user = $userMap->get($item->user_id);

            return [
                'id' => $item->id,
                'order_no' => $item->order_no,
                'user_id' => $item->user_id,
                'user_name' => $user?->username ?? '',
                'user_email' => $user?->email ?? '',
                'order_type' => $item->order_type,
                'order_type_text' => PayEnum::$orderTypeArr[$item->order_type] ?? '',
                'title' => $item->title,
                'total_amount' => $item->total_amount,
                'discount_amount' => $item->discount_amount,
                'pay_amount' => $item->pay_amount,
                'pay_status' => $item->pay_status,
                'pay_status_text' => PayEnum::$payStatusArr[$item->pay_status] ?? '',
                'biz_status' => $item->biz_status,
                'biz_status_text' => PayEnum::$bizStatusArr[$item->biz_status] ?? '',
                'admin_remark' => $item->admin_remark,
                'pay_time' => $item->pay_time,
                'created_at' => $item->created_at,
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

    public function detail($request): array
    {
        $param = $this->validate($request, OrderValidate::detail());
        $order = $this->dep(OrderDep::class)->getOrFail($param['id']);
        $user = $this->dep(UsersDep::class)->find($order->user_id);
        $items = $this->dep(OrderItemDep::class)->getByOrderId((int) $order->id);

        $channel = null;
        if ($order->channel_id) {
            $ch = $this->dep(PayChannelDep::class)->find((int) $order->channel_id);
            if ($ch) {
                $channel = ['id' => $ch->id, 'name' => $ch->name, 'channel' => $ch->channel];
            }
        }

        return self::success([
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'user_id' => $order->user_id,
                'user_name' => $user?->username ?? '',
                'user_email' => $user?->email ?? '',
                'order_type' => $order->order_type,
                'order_type_text' => PayEnum::$orderTypeArr[$order->order_type] ?? '',
                'biz_type' => $order->biz_type,
                'biz_id' => $order->biz_id,
                'title' => $order->title,
                'total_amount' => $order->total_amount,
                'discount_amount' => $order->discount_amount,
                'pay_amount' => $order->pay_amount,
                'pay_status' => $order->pay_status,
                'pay_status_text' => PayEnum::$payStatusArr[$order->pay_status] ?? '',
                'biz_status' => $order->biz_status,
                'biz_status_text' => PayEnum::$bizStatusArr[$order->biz_status] ?? '',
                'pay_time' => $order->pay_time,
                'expire_time' => $order->expire_time,
                'close_time' => $order->close_time,
                'close_reason' => $order->close_reason,
                'biz_done_at' => $order->biz_done_at,
                'admin_remark' => $order->admin_remark,
                'channel' => $channel,
                'pay_method' => $order->pay_method,
                'extra' => $order->extra ? json_decode($order->extra, true) : [],
                'created_at' => $order->created_at,
            ],
            'items' => $items->map(fn($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'amount' => $item->amount,
            ]),
        ]);
    }

    public function statusCount($request): array
    {
        $counts = $this->dep(OrderDep::class)->countByStatus();
        $result = [];
        foreach (PayEnum::$payStatusArr as $key => $label) {
            $result[$key] = ['label' => $label, 'count' => $counts[$key] ?? 0];
        }

        return self::success(['counts' => $result]);
    }

    public function close($request): array
    {
        $param = $this->validate($request, OrderValidate::close());
        $order = $this->dep(OrderDep::class)->getOrFail($param['id']);

        self::throwIf(
            !in_array((int) $order->pay_status, [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING], true),
            '该订单状态不允许关闭'
        );

        $result = $this->svc(PayDomainService::class)->syncPaidOrCloseOrder(
            (string) $order->order_no,
            $param['reason'] ?? '管理员关闭',
            'admin_close'
        );

        self::throwIf($result === 'deferred', '第三方状态确认失败，请稍后重试');

        return self::success();
    }

    public function remark($request): array
    {
        $param = $this->validate($request, OrderValidate::remark());
        $this->dep(OrderDep::class)->getOrFail($param['id']);
        $this->dep(OrderDep::class)->update($param['id'], ['admin_remark' => $param['remark'] ?? '']);

        return self::success();
    }
}

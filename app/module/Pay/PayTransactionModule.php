<?php

namespace app\module\Pay;

use app\dep\Pay\OrderDep;
use app\dep\Pay\PayChannelDep;
use app\dep\Pay\PayTransactionDep;
use app\dep\User\UsersDep;
use app\enum\PayEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\validate\Pay\PayTransactionValidate;

/**
 * 支付流水模块
 */
class PayTransactionModule extends BaseModule
{
    /** 初始化 */
    public function init($request): array
    {
        $dict = $this->svc(DictService::class)
            ->setPayChannelArr()
            ->getDict();

        $dict['txn_status_arr'] = DictService::enumToDict(PayEnum::$txnStatusArr);

        return self::success(['dict' => $dict]);
    }

    /** 列表 */
    public function list($request): array
    {
        $param = $this->validate($request, PayTransactionValidate::list());
        $res = $this->dep(PayTransactionDep::class)->list($param);
        $userMap = $this->dep(UsersDep::class)->getMap($res->pluck('user_id')->all(), ['id', 'username', 'email']);

        $list = $res->map(function ($item) use ($userMap) {
            $user = $userMap->get($item->user_id);

            return [
                'id'            => $item->id,
                'transaction_no' => $item->transaction_no,
                'order_no'      => $item->order_no,
                'user_id'       => $item->user_id,
                'user_name'     => $user?->username ?? '',
                'user_email'    => $user?->email ?? '',
                'attempt_no'    => $item->attempt_no,
                'channel'       => $item->channel,
                'channel_text'  => PayEnum::$channelArr[$item->channel] ?? '',
                'pay_method'    => $item->pay_method,
                'amount'        => $item->amount,
                'trade_no'      => $item->trade_no,
                'trade_status'  => $item->trade_status,
                'status'        => $item->status,
                'status_text'   => PayEnum::$txnStatusArr[$item->status] ?? '',
                'paid_at'       => $item->paid_at,
                'created_at'    => $item->created_at,
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
        $param = $this->validate($request, PayTransactionValidate::detail());
        $txn = $this->dep(PayTransactionDep::class)->getOrFail($param['id']);

        $channel = null;
        if ($txn->channel_id) {
            $ch = $this->dep(PayChannelDep::class)->find($txn->channel_id);
            if ($ch) {
                $channel = ['id' => $ch->id, 'name' => $ch->name, 'channel' => $ch->channel];
            }
        }

        $order = null;
        if ($txn->order_id) {
            $o = $this->dep(OrderDep::class)->find($txn->order_id);
            if ($o) {
                $user = $this->dep(UsersDep::class)->find($o->user_id);
                $order = [
                    'id'         => $o->id,
                    'order_no'   => $o->order_no,
                    'user_id'    => $o->user_id,
                    'user_name'  => $user?->username ?? '',
                    'user_email' => $user?->email ?? '',
                    'title'      => $o->title,
                    'pay_amount' => $o->pay_amount,
                    'pay_status' => $o->pay_status,
                ];
            }
        }

        return self::success([
            'transaction' => [
                'id'            => $txn->id,
                'transaction_no' => $txn->transaction_no,
                'order_no'      => $txn->order_no,
                'attempt_no'    => $txn->attempt_no,
                'channel'       => $txn->channel,
                'pay_method'    => $txn->pay_method,
                'amount'        => $txn->amount,
                'trade_no'      => $txn->trade_no,
                'trade_status'  => $txn->trade_status,
                'status'        => $txn->status,
                'status_text'   => PayEnum::$txnStatusArr[$txn->status] ?? '',
                'paid_at'       => $txn->paid_at,
                'closed_at'     => $txn->closed_at,
                'channel_resp'  => $this->normalizeJsonField($txn->channel_resp),
                'raw_notify'    => $this->normalizeJsonField($txn->raw_notify),
                'created_at'    => $txn->created_at,
            ],
            'channel' => $channel,
            'order'   => $order,
        ]);
    }

    /**
     * PayTransactionModel 已对 JSON 字段做 casts，这里兼容数组/字符串两种来源。
     */
    private function normalizeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}

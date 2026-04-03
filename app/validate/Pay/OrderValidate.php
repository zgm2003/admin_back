<?php

namespace app\validate\Pay;

use app\enum\PayEnum;
use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class OrderValidate
{
    public static function list(): array
    {
        return [
            'page'        => v::optional(v::intVal()->positive()),
            'page_size'  => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'order_type' => v::optional(v::intVal()->in(array_keys(PayEnum::$orderTypeArr))),
            'pay_status' => v::optional(v::intVal()->in(array_keys(PayEnum::$payStatusArr))),
            'order_no'   => v::optional(v::stringType()),
            'user_id'    => v::optional(v::intVal()->positive()),
            'start_date' => v::optional(v::stringType()->length(0, 20)),
            'end_date'   => v::optional(v::stringType()->length(0, 20)),
        ];
    }

    public static function detail(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('订单ID'),
        ];
    }

    public static function close(): array
    {
        return [
            'id'     => v::intVal()->positive()->setName('订单ID'),
            'reason' => v::optional(v::stringType()->length(1, 100))->setName('关闭原因'),
        ];
    }

    public static function cancelOrder(): array
    {
        return [
            'order_no' => v::stringType()->length(1, 64)->setName('订单号'),
            'reason'   => v::optional(v::stringType()->length(1, 100))->setName('关闭原因'),
        ];
    }

    public static function recharge(): array
    {
        return [
            'amount' => v::intVal()->min(1)->setName('充值金额'),
            'pay_method' => v::stringType()->in(array_keys(PayEnum::$methodArr))->setName('支付方式'),
            'channel_id' => v::intVal()->positive()->setName('支付渠道ID'),
        ];
    }

    public static function createPay(): array
    {
        return [
            'order_no'   => v::stringType()->length(1, 64)->setName('订单号'),
            'pay_method' => v::optional(v::stringType()->in(array_keys(PayEnum::$methodArr)))->setName('支付方式'),
            'return_url' => v::optional(v::stringType()->length(1, 1024))->setName('回跳地址'),
        ];
    }

    public static function remark(): array
    {
        return [
            'id'    => v::intVal()->positive()->setName('订单ID'),
            'remark'=> v::stringType()->length(1, 500)->setName('备注'),
        ];
    }
}

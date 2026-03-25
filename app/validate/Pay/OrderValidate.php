<?php

namespace app\validate\Pay;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class OrderValidate
{
    public static function list(): array
    {
        return [
            'page'        => v::optional(v::intVal()->positive()),
            'page_size'  => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'order_type' => v::optional(v::intVal()->in(array_keys(\app\enum\PayEnum::$orderTypeArr))),
            'pay_status' => v::optional(v::intVal()->in(array_keys(\app\enum\PayEnum::$payStatusArr))),
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

    public static function remark(): array
    {
        return [
            'id'    => v::intVal()->positive()->setName('订单ID'),
            'remark'=> v::stringType()->length(1, 500)->setName('备注'),
        ];
    }
}

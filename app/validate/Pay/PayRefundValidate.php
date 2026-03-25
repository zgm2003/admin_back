<?php

namespace app\validate\Pay;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class PayRefundValidate
{
    public static function list(): array
    {
        return [
            'page'       => v::optional(v::intVal()->positive()),
            'page_size'  => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'status'     => v::optional(v::intVal()->in(array_keys(\app\enum\PayEnum::$refundRecordStatusArr))),
            'refund_no'  => v::optional(v::stringType()),
            'order_no'   => v::optional(v::stringType()),
            'start_date' => v::optional(v::stringType()->length(0, 20)),
            'end_date'   => v::optional(v::stringType()->length(0, 20)),
        ];
    }

    public static function detail(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('退款ID'),
        ];
    }

    public static function apply(): array
    {
        return [
            'order_id'       => v::intVal()->positive()->setName('订单ID'),
            'transaction_id'  => v::optional(v::intVal()->positive())->setName('原支付流水ID'),
            'refund_amount'  => v::intVal()->positive()->setName('退款金额'),
            'reason'         => v::optional(v::stringType()->length(0, 255))->setName('退款原因'),
        ];
    }
}

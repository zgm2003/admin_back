<?php

namespace app\validate\Pay;

use app\enum\PayEnum;
use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class PayTransactionValidate
{
    public static function list(): array
    {
        return [
            'current_page' => v::optional(v::intVal()->positive()),
            'page_size'  => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'order_no'   => v::optional(v::stringType()),
            'transaction_no' => v::optional(v::stringType()),
            'user_id'    => v::optional(v::intVal()->positive()),
            'status'     => v::optional(v::intVal()->in(array_keys(PayEnum::$txnStatusArr))),
            'channel'    => v::optional(v::intVal()->in(array_keys(PayEnum::$channelArr))),
            'start_date' => v::optional(v::stringType()->length(0, 20)),
            'end_date'   => v::optional(v::stringType()->length(0, 20)),
        ];
    }

    public static function detail(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('流水ID'),
        ];
    }
}

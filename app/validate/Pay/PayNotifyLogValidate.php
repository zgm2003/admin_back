<?php

namespace app\validate\Pay;

use app\enum\CommonEnum;
use app\enum\PayEnum;
use Respect\Validation\Validator as v;

class PayNotifyLogValidate
{
    public static function list(): array
    {
        return [
            'page' => v::optional(v::intVal()->positive()),
            'page_size' => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'channel' => v::optional(v::intVal()->in(array_keys(PayEnum::$channelArr))),
            'notify_type' => v::optional(v::intVal()->in(array_keys(PayEnum::$notifyTypeArr))),
            'process_status' => v::optional(v::intVal()->in(array_keys(PayEnum::$notifyProcessStatusArr))),
            'transaction_no' => v::optional(v::stringType()),
            'start_date' => v::optional(v::stringType()->length(0, 20)),
            'end_date' => v::optional(v::stringType()->length(0, 20)),
        ];
    }

    public static function detail(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('回调日志ID'),
        ];
    }
}

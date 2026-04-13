<?php

namespace app\validate\Pay;

use app\enum\PayEnum;
use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class PayReconcileValidate
{
    public static function list(): array
    {
        return [
            'current_page' => v::optional(v::intVal()->positive()),
            'page_size'  => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'channel'    => v::optional(v::intVal()->in(array_keys(PayEnum::$channelArr))),
            'status'     => v::optional(v::intVal()->in(array_keys(PayEnum::$reconcileStatusArr))),
            'bill_type'  => v::optional(v::intVal()->in([1])),
            'start_date' => v::optional(v::stringType()->length(0, 20)),
            'end_date'   => v::optional(v::stringType()->length(0, 20)),
        ];
    }

    public static function detail(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('对账任务ID'),
        ];
    }

    public static function retry(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('对账任务ID'),
        ];
    }

    public static function download(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('对账任务ID'),
            'type' => v::stringType()->in(['platform', 'local', 'diff'])->setName('文件类型'),
        ];
    }
}

<?php

namespace app\validate\Pay;

use app\enum\PayEnum;
use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class UserWalletValidate
{
    public static function list(): array
    {
        return [
            'current_page' => v::optional(v::intVal()->positive()),
            'page_size'  => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'user_id'    => v::optional(v::intVal()->positive()),
            'start_date' => v::optional(v::stringType()->length(0, 20)),
            'end_date'   => v::optional(v::stringType()->length(0, 20)),
        ];
    }

    public static function transactions(): array
    {
        return [
            'current_page' => v::optional(v::intVal()->positive()),
            'page_size'  => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'user_id'    => v::optional(v::intVal()->positive()),
            'type'       => v::optional(v::intVal()->in(array_keys(PayEnum::$walletTypeArr))),
            'start_date' => v::optional(v::stringType()->length(0, 20)),
            'end_date'   => v::optional(v::stringType()->length(0, 20)),
        ];
    }

    public static function adjust(): array
    {
        return [
            'user_id' => v::intVal()->positive()->setName('用户ID'),
            'delta'   => v::intVal()->setName('调整金额'),
            'reason'  => v::optional(v::stringType()->length(0, 255))->setName('调账原因'),
        ];
    }
}

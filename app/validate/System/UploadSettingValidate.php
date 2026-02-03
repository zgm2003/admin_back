<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class UploadSettingValidate
{
    public static function list(): array
    {
        return [
            'page_size'    => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page' => v::intVal()->positive()->setName('当前页'),
            'remark'       => v::optional(v::stringType()),
            'status'       => v::optional(v::intVal()),
            'driver_id'    => v::optional(v::intVal()),
            'rule_id'      => v::optional(v::intVal()),
        ];
    }

    public static function add(): array
    {
        return [
            'driver_id' => v::intVal()->setName('driver_id'),
            'rule_id'   => v::intVal()->setName('rule_id'),
            'status'    => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('status'),
            'remark'    => v::optional(v::stringType())->setName('remark'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'        => v::intVal()->setName('id'),
            'driver_id' => v::intVal()->setName('driver_id'),
            'rule_id'   => v::intVal()->setName('rule_id'),
            'status'    => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('status'),
            'remark'    => v::optional(v::stringType())->setName('remark'),
        ];
    }
    
    public static function status(): array
    {
        return [
            'id'     => v::intVal()->setName('id'),
            'status' => v::intVal()->between(1, 2)->setName('status'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }
}

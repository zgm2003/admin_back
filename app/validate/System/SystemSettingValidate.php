<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class SystemSettingValidate
{
    public static function add(): array
    {
        return [
            'key'    => v::length(1, 100)->setName('key'),
            'value'  => v::optional(v::stringType())->setName('value'),
            'type'   => v::intVal()->in([1,2,3,4])->setName('type'),
            'remark' => v::optional(v::length(0, 255))->setName('remark'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'     => v::intVal()->setName('ID'),
            'value'  => v::optional(v::stringType())->setName('value'),
            'type'   => v::intVal()->in([1,2,3,4])->setName('type'),
            'remark' => v::optional(v::length(0, 255))->setName('remark'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'    => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page' => v::intVal()->positive()->setName('当前页'),
            'key'          => v::optional(v::stringType()),
            'status'       => v::optional(v::intVal()->in([1,2])),
        ];
    }

    public static function status(): array
    {
        return [
            'id'     => v::intVal()->setName('ID'),
            'status' => v::intVal()->in([1,2])->setName('状态'),
        ];
    }
}


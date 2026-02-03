<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class UsersListValidate
{
    public static function list(): array
    {
        return [
            'page_size'      => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page'   => v::intVal()->positive()->setName('当前页'),
            'keyword'        => v::optional(v::stringType()),
            'username'       => v::optional(v::stringType()),
            'email'          => v::optional(v::stringType()),
            'detail_address' => v::optional(v::stringType()),
            'address_id'     => v::optional(v::oneOf(v::intVal(), v::arrayType())),
            'address'        => v::optional(v::oneOf(v::intVal(), v::arrayType())),
            'role_id'        => v::optional(v::intVal()),
            'sex'            => v::optional(v::intVal()->in(array_keys(CommonEnum::$sexArr))),
            'date'           => v::optional(v::arrayType()),
        ];
    }

    public static function edit(): array
    {
        return [
            'id' => v::intVal()->setName('用户ID'),
            'username' => v::length(1, 64)->setName('用户名'),
            'avatar' => v::optional(v::stringType()),
            'role_id' => v::intVal()->setName('角色'),
            'sex' => v::intVal()->in(array_keys(CommonEnum::$sexArr))->setName('性别'),
            'address' => v::intVal()->setName('地址'),
            'detail_address' => v::optional(v::stringType()),
            'bio' => v::optional(v::stringType()),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }

    public static function batchEdit(): array
    {
        return [
            'ids' => v::arrayType()->setName('ids'),
            'field' => v::stringType()->setName('字段'),
            'sex' => v::optional(v::intVal()->in(array_keys(CommonEnum::$sexArr))),
            'address' => v::optional(v::intVal()),
            'detail_address' => v::optional(v::stringType()),
        ];
    }

    public static function export(): array
    {
        return [
            'ids' => v::arrayType()->setName('ids'),
        ];
    }
}


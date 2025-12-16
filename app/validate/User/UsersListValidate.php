<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;

class UsersListValidate
{
    public static function edit(): array
    {
        return [
            'id' => v::intVal()->setName('用户ID'),
            'username' => v::length(1, 64)->setName('用户名'),
            'avatar' => v::optional(v::stringType()),
            'role_id' => v::intVal()->setName('角色'),
            'sex' => v::intVal()->setName('性别'),
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
            'sex' => v::optional(v::intVal()),
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


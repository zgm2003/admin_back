<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;

class RoleValidate
{
    public static function add(): array
    {
        return [
            'name'          => v::length(1, 64)->setName('角色名'),
            'permission_id' => v::arrayType()->setName('权限'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'            => v::intVal()->setName('ID'),
            'name'          => v::length(1, 64)->setName('角色名'),
            'permission_id' => v::arrayType()->setName('权限'),
        ];
    }

    public static function setDefault(): array
    {
        return [
            'id' => v::intVal()->setName('ID'),
        ];
    }
}


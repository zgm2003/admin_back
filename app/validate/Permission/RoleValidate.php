<?php

namespace app\validate\Permission;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class RoleValidate
{
    public static function list(): array
    {
        return [
            'page_size'    => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page' => v::intVal()->positive()->setName('当前页'),
            'name'         => v::optional(v::stringType()),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }

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

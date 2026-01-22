<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;

class UsersQuickEntryValidate
{
    public static function add(): array
    {
        return [
            'permission_id' => v::intVal()->setName('权限ID'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::intVal()->setName('ID'),
        ];
    }
}

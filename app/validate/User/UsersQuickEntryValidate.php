<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;

class UsersQuickEntryValidate
{
    public static function add(): array
    {
        return [
            'permission_id' => v::intVal()->min(1)->setName('??ID'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::intVal()->setName('ID'),
        ];
    }

    public static function sort(): array
    {
        return [
            'items' => v::arrayType()->setName('???'),
        ];
    }
}

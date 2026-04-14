<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;

class UsersQuickEntryValidate
{
    public static function save(): array
    {
        return [
            'permission_ids' => v::arrayType()->each(v::intVal()->min(1))->setName('权限ID列表'),
        ];
    }
}

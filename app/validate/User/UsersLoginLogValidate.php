<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;

class UsersLoginLogValidate
{
    public static function listCursor(): array
    {
        return [
            'page_size'     => v::optional(v::intVal()->min(1)->max(100)),
            'cursor'        => v::optional(v::intVal()),
            'user_id'       => v::optional(v::intVal()),
            'login_account' => v::optional(v::stringType()),
            'login_type'    => v::optional(v::stringType()),
            'ip'            => v::optional(v::stringType()),
            'platform'      => v::optional(v::stringType()),
            'is_success'    => v::optional(v::intVal()),
            'date'          => v::optional(v::arrayType()),
        ];
    }
}

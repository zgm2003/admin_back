<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class UsersLoginLogValidate
{
    public static function list(): array
    {
        return [
            'page_size'     => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page'  => v::intVal()->positive()->setName('当前页'),
            'user_id'       => v::optional(v::intVal()),
            'login_account' => v::optional(v::stringType()),
            'login_type'    => v::optional(v::stringType()),
            'ip'            => v::optional(v::stringType()),
            'platform'      => v::optional(v::stringType()),
            'is_success'    => v::optional(v::intVal()),
            'date'          => v::optional(v::arrayType()),
        ];
    }

    public static function listCursor(): array
    {
        return [
            'page_size'     => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'cursor'        => v::optional(v::intVal())->setName('游标'),
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

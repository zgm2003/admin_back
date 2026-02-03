<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class UserSessionValidate
{
    public static function list(): array
    {
        return [
            'page_size'    => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page' => v::intVal()->positive()->setName('当前页'),
            'username'     => v::optional(v::stringType()),
            'platform'     => v::optional(v::stringType()),
            'status'       => v::optional(v::stringType()),
        ];
    }

    public static function kick(): array
    {
        return [
            'id' => v::intVal()->setName('会话ID'),
        ];
    }

    public static function batchKick(): array
    {
        return [
            'ids' => v::arrayType()->each(v::intVal())->setName('会话ID列表'),
        ];
    }
}

<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class OperationLogValidate
{
    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page' => v::optional(v::intVal()),
            'user_id'      => v::optional(v::intVal()),
            'action'       => v::optional(v::stringType()),
            'date'         => v::optional(v::arrayType()),
        ];
    }

}

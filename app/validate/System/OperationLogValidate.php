<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;

class OperationLogValidate
{
    public static function del(): array
    {
        return [
            'id' => v::intVal()->setName('ID'),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()),
            'current_page' => v::optional(v::intVal()),
            'user_id'      => v::optional(v::intVal()),
            'action'       => v::optional(v::stringType()),
        ];
    }
}


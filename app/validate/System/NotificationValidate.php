<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;

class NotificationValidate
{
    public static function list(): array
    {
        return [
            'page_size' => v::optional(v::intVal()->between(1, 100))->setName('每页数量'),
            'cursor'    => v::optional(v::intVal())->setName('游标'),
        ];
    }

    public static function read(): array
    {
        return [
            'id' => v::optional(v::intVal())->setName('ID'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::intVal()->setName('ID'),
        ];
    }
}

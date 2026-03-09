<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class ExportTaskValidate
{
    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page' => v::optional(v::intVal()->positive()),
            'status'       => v::optional(v::intVal()),
            'title'        => v::optional(v::stringType()),
            'file_name'    => v::optional(v::stringType()),
            'user_id'      => v::optional(v::intVal()),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(
                v::intVal()->positive(),
                v::arrayType()->notEmpty()->each(v::intVal()->positive())
            )->setName('任务ID'),
        ];
    }
}

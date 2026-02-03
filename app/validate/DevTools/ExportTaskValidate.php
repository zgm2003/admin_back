<?php

namespace app\validate\DevTools;

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
            'id' => v::intVal()->setName('任务ID'),
        ];
    }

    public static function batchDel(): array
    {
        return [
            'ids' => v::arrayType()->notEmpty()->setName('任务ID列表'),
        ];
    }
}

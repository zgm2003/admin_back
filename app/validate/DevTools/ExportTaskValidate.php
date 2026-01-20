<?php

namespace app\validate\DevTools;

use Respect\Validation\Validator as v;

class ExportTaskValidate
{
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

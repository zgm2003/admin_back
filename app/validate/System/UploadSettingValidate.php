<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;

class UploadSettingValidate
{
    public static function add(): array
    {
        return [
            'driver_id' => v::intVal()->setName('driver_id'),
            'rule_id'   => v::intVal()->setName('rule_id'),
            'status'    => v::intVal()->between(1, 2)->setName('status'),
            'remark'    => v::optional(v::stringType())->setName('remark'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'        => v::intVal()->setName('id'),
            'driver_id' => v::intVal()->setName('driver_id'),
            'rule_id'   => v::intVal()->setName('rule_id'),
            'status'    => v::intVal()->between(1, 2)->setName('status'),
            'remark'    => v::optional(v::stringType())->setName('remark'),
        ];
    }
    
    public static function status(): array
    {
        return [
            'id'     => v::intVal()->setName('id'),
            'status' => v::intVal()->between(1, 2)->setName('status'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }
}

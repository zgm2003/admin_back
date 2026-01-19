<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;

class WebSocketValidate
{
    public static function bind(): array
    {
        return [
            'client_id' => v::stringType()->notEmpty()->setName('client_id'),
        ];
    }

    public static function joinGroup(): array
    {
        return [
            'client_id' => v::stringType()->notEmpty()->setName('client_id'),
            'group'     => v::stringType()->notEmpty()->setName('group'),
        ];
    }

    public static function pushToUser(): array
    {
        return [
            'uid'  => v::intVal()->setName('uid'),
            'type' => v::optional(v::stringType())->setName('type'),
            'data' => v::optional(v::arrayType())->setName('data'),
        ];
    }

    public static function broadcast(): array
    {
        return [
            'type' => v::optional(v::stringType())->setName('type'),
            'data' => v::optional(v::arrayType())->setName('data'),
        ];
    }
}

<?php

namespace app\validate\Test;

use Respect\Validation\Validator as v;

class TestValidate
{
    public static function add(): array
    {
        return [
            'password'    => v::length(6, 64)->setName('密码'),
            'newpassword' => v::length(6, 64)->setName('新密码'),
            'respassword' => v::length(6, 64)->setName('确认密码'),
            'mobile_id'   => v::optional(v::stringType()),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::intVal()->setName('ID'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'          => v::intVal()->setName('ID'),
            'password'    => v::length(6, 64)->setName('密码'),
            'newpassword' => v::length(6, 64)->setName('新密码'),
            'respassword' => v::length(6, 64)->setName('确认密码'),
            'mobile_id'   => v::optional(v::stringType()),
        ];
    }

    public static function batchEdit(): array
    {
        return [
            'ids'    => v::arrayType()->setName('ids'),
            'field'  => v::stringType()->setName('字段'),
            'status' => v::optional(v::intVal()),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()),
            'current_page' => v::optional(v::intVal()),
        ];
    }

    public static function sendTest(): array
    {
        return [
            'id'  => v::optional(v::intVal()),
            'abc' => v::optional(v::stringType()),
        ];
    }
}


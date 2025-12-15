<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;

class UsersValidate
{
    public static function register(): array
    {
        return [
            'username'    => v::length(2, 64)->setName('用户名'),
            'email'       => v::email()->setName('邮箱'),
            'password'    => v::length(6, 64)->setName('密码'),
            'respassword' => v::length(6, 64)->setName('确认密码'),
            'code'        => v::digit()->length(6, 6)->setName('验证码'),
        ];
    }

    public static function login(): array
    {
        return [
            'email'    => v::email()->setName('邮箱'),
            'password' => v::length(6, 64)->setName('密码'),
        ];
    }

    public static function sendCode(): array
    {
        return [
            'email'  => v::email()->setName('邮箱'),
            'status' => v::optional(v::intVal()),
        ];
    }
}


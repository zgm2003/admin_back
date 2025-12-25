<?php

namespace app\validate\User;

use app\enum\SystemEnum;
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
            'login_account' => v::stringType()->length(1, 120)->setName('账号'),
            'password'      => v::optional(v::length(6, 64))->setName('密码'),
            'code'          => v::optional(v::digit()->length(6, 6))->setName('验证码'),
            'login_type'    => v::stringType()->in(array_keys(SystemEnum::$loginTypeArr))->setName('登录类型')
        ];
    }

    public static function sendCode(): array
    {
        return [
            'login_account' => v::stringType()->setName('账号'),
            'status' => v::optional(v::intVal()),
        ];
    }
}


<?php

namespace app\validate\User;

use app\enum\EmailEnum;
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
            'account' => v::stringType()->setName('账号'),
            'scene' => v::in(array_keys(EmailEnum::$sceneArr))->setName('场景'),
        ];
    }

    public static function updatePhone(): array
    {
        return [
            'phone' => v::stringType()->length(11, 11)->setName('手机号'),
            'code' => v::digit()->length(6, 6)->setName('验证码'),
        ];
    }

    public static function updateEmail(): array
    {
        return [
            'email' => v::email()->setName('邮箱'),
            'code' => v::digit()->length(6, 6)->setName('验证码'),
        ];
    }

    public static function updatePassword(): array
    {
        return [
            'verify_type' => v::in(array_keys(SystemEnum::$verifyTypeArr))->setName('验证类型'),
            'old_password' => v::optional(v::stringType())->setName('原密码'),
            'code' => v::optional(v::digit()->length(6, 6))->setName('验证码'),
            'new_password' => v::length(6, 64)->setName('新密码'),
            'confirm_password' => v::length(6, 64)->setName('确认密码'),
        ];
    }

    public static function forgetPassword(): array
    {
        return [
            'account' => v::stringType()->length(1, 120)->setName('账号'),
            'new_password' => v::length(6, 64)->setName('新密码'),
            'confirm_password' => v::length(6, 64)->setName('确认密码'),
            'code' => v::digit()->length(6, 6)->setName('验证码'),
        ];
    }

    public static function editPersonal(): array
    {
        return [
            'username' => v::length(1, 50)->setName('用户名'),
            'avatar' => v::optional(v::stringType()),
            'sex' => v::intVal()->setName('性别'),
            'birthday' => v::optional(v::stringType())->setName('生日'),
            'address' => v::intVal()->setName('地址'),
            'detail_address' => v::optional(v::stringType()),
            'bio' => v::optional(v::stringType()),
        ];
    }

    public static function editPassword(): array
    {
        return [
            'password' => v::length(6, 64)->setName('原始密码'),
            'newpassword' => v::length(6, 64)->setName('新密码'),
            'respassword' => v::length(6, 64)->setName('确认新密码'),
        ];
    }

    public static function initPersonal(): array
    {
        return [
            'user_id' => v::intVal()->setName('用户ID'),
        ];
    }
}


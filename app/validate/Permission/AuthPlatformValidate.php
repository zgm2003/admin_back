<?php

namespace app\validate\Permission;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class AuthPlatformValidate
{
    public static function add(): array
    {
        return [
            'code'           => v::regex('/^[a-z][a-z0-9_]{1,48}$/')->setName('平台标识'),
            'name'           => v::length(1, 100)->setName('平台名称'),
            'login_types'    => v::arrayType()->each(v::stringType()->in(['password', 'email', 'phone']))->setName('登录方式'),
            'access_ttl'     => v::intVal()->between(60, 2592000)->setName('access_token有效期'),
            'refresh_ttl'    => v::intVal()->between(60, 31536000)->setName('refresh_token有效期'),
            'bind_platform'  => v::intVal()->in([1, 2])->setName('绑定平台'),
            'bind_device'    => v::intVal()->in([1, 2])->setName('绑定设备'),
            'bind_ip'        => v::intVal()->in([1, 2])->setName('绑定IP'),
            'single_session' => v::intVal()->in([1, 2])->setName('单端登录'),
            'max_sessions'   => v::intVal()->between(0, 100)->setName('最大会话数'),
            'allow_register' => v::intVal()->in([1, 2])->setName('允许注册'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'             => v::intVal()->setName('ID'),
            'name'           => v::length(1, 100)->setName('平台名称'),
            'login_types'    => v::arrayType()->each(v::stringType()->in(['password', 'email', 'phone']))->setName('登录方式'),
            'access_ttl'     => v::intVal()->between(60, 2592000)->setName('access_token有效期'),
            'refresh_ttl'    => v::intVal()->between(60, 31536000)->setName('refresh_token有效期'),
            'bind_platform'  => v::intVal()->in([1, 2])->setName('绑定平台'),
            'bind_device'    => v::intVal()->in([1, 2])->setName('绑定设备'),
            'bind_ip'        => v::intVal()->in([1, 2])->setName('绑定IP'),
            'single_session' => v::intVal()->in([1, 2])->setName('单端登录'),
            'max_sessions'   => v::intVal()->between(0, 100)->setName('最大会话数'),
            'allow_register' => v::intVal()->in([1, 2])->setName('允许注册'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'    => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page' => v::intVal()->positive()->setName('当前页'),
            'name'         => v::optional(v::stringType()),
            'status'       => v::optional(v::intVal()->in(\array_keys(CommonEnum::$statusArr))),
        ];
    }

    public static function status(): array
    {
        return [
            'id'     => v::intVal()->setName('ID'),
            'status' => v::intVal()->in(\array_keys(CommonEnum::$statusArr))->setName('状态'),
        ];
    }
}

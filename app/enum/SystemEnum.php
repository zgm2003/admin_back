<?php

namespace app\enum;

class SystemEnum
{
    const VALUE_STRING = 1;
    const VALUE_NUMBER = 2;
    const VALUE_BOOL   = 3;
    const VALUE_JSON   = 4;

    public static $valueTypeArr = [
        self::VALUE_STRING => '字符串',
        self::VALUE_NUMBER => '数字',
        self::VALUE_BOOL   => '布尔',
        self::VALUE_JSON   => 'JSON',
    ];

    const LOGIN_TYPE_EMAIL = 'email';
    const LOGIN_TYPE_PHONE = 'phone';
    const LOGIN_TYPE_PASSWORD = 'password';

    public static $loginTypeArr = [
        self::LOGIN_TYPE_EMAIL => '邮箱登录',
        self::LOGIN_TYPE_PHONE => '手机号登录',
        self::LOGIN_TYPE_PASSWORD => '密码登录',
    ];

    // 密码修改验证方式
    const VERIFY_TYPE_PASSWORD = 'password';
    const VERIFY_TYPE_CODE = 'code';

    public static $verifyTypeArr = [
        self::VERIFY_TYPE_PASSWORD => '原密码验证',
        self::VERIFY_TYPE_CODE => '验证码验证',
    ];
}


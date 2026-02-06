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

    // 日志级别
    const LOG_LEVEL_DEBUG    = 'DEBUG';
    const LOG_LEVEL_INFO     = 'INFO';
    const LOG_LEVEL_WARNING  = 'WARNING';
    const LOG_LEVEL_ERROR    = 'ERROR';
    const LOG_LEVEL_CRITICAL = 'CRITICAL';

    public static $logLevelArr = [
        self::LOG_LEVEL_DEBUG    => 'DEBUG',
        self::LOG_LEVEL_INFO     => 'INFO',
        self::LOG_LEVEL_WARNING  => 'WARNING',
        self::LOG_LEVEL_ERROR    => 'ERROR',
        self::LOG_LEVEL_CRITICAL => 'CRITICAL',
    ];

    // 日志读取行数
    const LOG_TAIL_100  = 100;
    const LOG_TAIL_300  = 300;
    const LOG_TAIL_500  = 500;
    const LOG_TAIL_1000 = 1000;
    const LOG_TAIL_2000 = 2000;

    public static $logTailArr = [
        self::LOG_TAIL_100  => '最近 100 行',
        self::LOG_TAIL_300  => '最近 300 行',
        self::LOG_TAIL_500  => '最近 500 行',
        self::LOG_TAIL_1000 => '最近 1000 行',
        self::LOG_TAIL_2000 => '最近 2000 行',
    ];
}


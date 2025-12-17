<?php

namespace app\enum;

class CommonEnum
{
    const DEFAULT_NULL = '-';
    const YES = 1;
    const NO = 2;
    const privince = '湖北省';
    const city = '武汉市';
    const area = '汉阳区';

    const DB_DEFAULT_TIME = '2000-01-01 00:00:00';

    const PLATFORM_admin = 'admin';
    const PLATFORM_WEB = 'web';
    const PLATFORM_APP = 'app';
    const PLATFORM_H5 = 'h5';
    const PLATFORM_MINI = 'mini';

    public static $isArr = [
        self::YES => "是",
        self::NO => "否",
    ];
    public static $statusArr = [
        self::YES => "启用",
        self::NO => "禁用",
    ];


    public static $platformArr = [
        self::PLATFORM_admin => "admin",
        self::PLATFORM_WEB => "web",
        self::PLATFORM_APP => "appp",
        self::PLATFORM_H5 => "h5",
        self::PLATFORM_MINI => "mini",

    ];
}

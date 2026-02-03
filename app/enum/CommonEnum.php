<?php

namespace app\enum;

class CommonEnum
{
    const DEFAULT_NULL = '-';

    const PAGE_SIZE_MIN = 1;
    const PAGE_SIZE_MAX = 50;
    const YES = 1;
    const NO = 2;
    const privince = '湖北省';
    const city = '武汉市';
    const area = '汉阳区';

    const DB_DEFAULT_TIME = '2000-01-01 00:00:00';

    // 性别常量
    const SEX_UNKNOWN = 0;
    const SEX_MALE = 1;
    const SEX_FEMALE = 2;

    public static $isArr = [
        self::YES => "是",
        self::NO => "否",
    ];
    public static $statusArr = [
        self::YES => "启用",
        self::NO => "禁用",
    ];

    public static $sexArr = [
        self::SEX_UNKNOWN => "未知",
        self::SEX_MALE => "男",
        self::SEX_FEMALE => "女",
    ];

}

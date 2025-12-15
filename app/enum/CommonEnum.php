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

    public static $isArr = [
        self::YES => "是",
        self::NO => "否",
    ];
    public static $statusArr = [
        self::YES => "启用",
        self::NO => "禁用",
    ];


}

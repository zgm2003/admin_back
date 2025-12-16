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
}


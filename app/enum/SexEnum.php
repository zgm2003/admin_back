<?php

namespace app\enum;

class SexEnum
{
    const UNKNOWN = 1;
    const MALE =  2;
    const FEMALE = 3;


    public static $SexArr = [
        self::UNKNOWN => "未知",
        self::MALE => "男",
        self::FEMALE => "女",
    ];


}

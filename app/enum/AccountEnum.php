<?php

namespace app\enum;

class AccountEnum
{
    const PINDUODUO = 'pinduoduo';
    const ALICLOUD = 'aliCloud';

    public static $platformArr = [
        self::PINDUODUO => "拼多多",
        self::ALICLOUD => "阿里云",
    ];


}

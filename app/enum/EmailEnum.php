<?php

namespace app\enum;

class EmailEnum
{
    const REGISTER = 1;
    const FORGETPASSWORD = 2;

    public static $statusArr = [
        self::REGISTER => "验证码注册",
        self::FORGETPASSWORD => "忘记密码",

    ];


}

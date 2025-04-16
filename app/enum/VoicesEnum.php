<?php

namespace app\enum;

class VoicesEnum
{
    const HZ_48000 = 1;
    const HZ_24000 = 2;
    const HZ_16000 = 3;
    const HZ_8000 = 4;

    public static $hzArr = [
        self::HZ_48000 => 48000,
        self::HZ_24000 => 24000,
        self::HZ_16000 => 16000,
        self::HZ_8000 => 8000,
    ];

    const QUALITY_1 = 1;
    const QUALITY_2 = 2;
    const QUALITY_3 = 3;

    public static $qualityArr = [
        self::QUALITY_1 => '标准版',
        self::QUALITY_2 => 'lite版',
        self::QUALITY_3 => '精品版',
    ];

}

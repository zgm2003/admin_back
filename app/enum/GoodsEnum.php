<?php

namespace app\enum;

class GoodsEnum
{
    const IMAGE = 1;
    const IMAGE_SUCCESS = 2;
    const OCR = 3;
    const OCR_ERROR = 4;
    const REVIEW = 5;
    const POINT = 6;
    const POINT_ERROR = 7;
    const POINT_SUCCESS = 8;
    const SPEECH = 9;
    const SPEECH_ERROR = 10;
    const SPEECH_SUCCESS = 11;

    public static $statusArr = [
        self::IMAGE => "取图中",
        self::IMAGE_SUCCESS => "取图成功",
        self::OCR => "OCR识别中",
        self::OCR_ERROR => "识别失败",
        self::REVIEW => "待审核",
        self::POINT => "卖点生成中",
        self::POINT_ERROR => "生成失败",
        self::POINT_SUCCESS => "生成成功/待审核",
        self::SPEECH => "语音合成中",
        self::SPEECH_ERROR => "合成失败",
        self::SPEECH_SUCCESS => "合成成功",
    ];

    const PLATFORM_PINDUODUO = 1;
    CONST PLATFORM_TAOBAO = 2;
    const PLATFORM_JD = 3;

    public static $platformArr = [
        self::PLATFORM_PINDUODUO => "拼多多",
        self::PLATFORM_TAOBAO => "淘宝",
        self::PLATFORM_JD => "京东",
    ];

}

<?php

namespace app\enum;

class GoodsEnum
{
    // 平台
    const PLATFORM_TAOBAO = 1;
    const PLATFORM_JD = 2;
    const PLATFORM_TMALL = 3;
    const PLATFORM_TMALL_CHAOSHI = 4;
    const PLATFORM_PDD = 5;
    const PLATFORM_DOUYIN = 6;
    const PLATFORM_KUAISHOU = 7;
    const PLATFORM_XIAOHONGSHU = 8;
    const PLATFORM_1688 = 9;
    const PLATFORM_VIP = 10;
    const PLATFORM_SUNING = 11;

    public static $platformArr = [
        self::PLATFORM_TAOBAO => '淘宝',
        self::PLATFORM_JD => '京东',
        self::PLATFORM_TMALL => '天猫',
        self::PLATFORM_TMALL_CHAOSHI => '天猫超市',
        self::PLATFORM_PDD => '拼多多',
        self::PLATFORM_DOUYIN => '抖音',
        self::PLATFORM_KUAISHOU => '快手',
        self::PLATFORM_XIAOHONGSHU => '小红书',
        self::PLATFORM_1688 => '1688',
        self::PLATFORM_VIP => '唯品会',
        self::PLATFORM_SUNING => '苏宁',
    ];

    // 状态（线性递进）
    const STATUS_PENDING    = 1;  // 待处理（刚入库）
    const STATUS_OCR        = 2;  // OCR识别中
    const STATUS_RECOGNIZED = 3;  // 已识别
    const STATUS_GENERATING = 4;  // 生成口播中
    const STATUS_GENERATED  = 5;  // 已生成
    const STATUS_TTS        = 6;  // 语音合成中
    const STATUS_COMPLETED  = 7;  // 已完成
    const STATUS_FAILED     = 8;  // 失败

    public static $statusArr = [
        self::STATUS_PENDING    => '待处理',
        self::STATUS_OCR        => 'OCR识别中',
        self::STATUS_RECOGNIZED => '已识别',
        self::STATUS_GENERATING => '生成口播中',
        self::STATUS_GENERATED  => '已生成',
        self::STATUS_TTS        => '语音合成中',
        self::STATUS_COMPLETED  => '已完成',
        self::STATUS_FAILED     => '失败',
    ];
}

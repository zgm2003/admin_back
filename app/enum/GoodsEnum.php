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

    public static $platformArr = [
        self::PLATFORM_TAOBAO => '淘宝',
        self::PLATFORM_JD => '京东',
        self::PLATFORM_TMALL => '天猫',
        self::PLATFORM_TMALL_CHAOSHI => '天猫超市',
        self::PLATFORM_PDD => '拼多多',
    ];

    // TTS音色
    const VOICE_XIAOXIAO   = 'zh-CN-XiaoxiaoNeural';   // 女-温暖
    const VOICE_XIAOYI     = 'zh-CN-XiaoyiNeural';     // 女-活泼
    const VOICE_YUNJIAN    = 'zh-CN-YunjianNeural';     // 男-激情
    const VOICE_YUNXI      = 'zh-CN-YunxiNeural';       // 男-阳光
    const VOICE_YUNXIA     = 'zh-CN-YunxiaNeural';      // 男-可爱
    const VOICE_YUNYANG    = 'zh-CN-YunyangNeural';     // 男-专业
    const VOICE_XIAOBEI    = 'zh-CN-liaoning-XiaobeiNeural'; // 女-东北方言
    const VOICE_XIAONI     = 'zh-CN-shaanxi-XiaoniNeural';  // 女-陕西方言

    public static $voiceArr = [
        self::VOICE_XIAOXIAO => '晓晓（女-温暖）',
        self::VOICE_XIAOYI   => '晓伊（女-活泼）',
        self::VOICE_YUNJIAN  => '云健（男-激情）',
        self::VOICE_YUNXI    => '云希（男-阳光）',
        self::VOICE_YUNXIA   => '云夏（男-可爱）',
        self::VOICE_YUNYANG  => '云扬（男-专业）',
        self::VOICE_XIAOBEI  => '晓北（女-东北方言）',
        self::VOICE_XIAONI   => '晓妮（女-陕西方言）',
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

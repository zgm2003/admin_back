<?php

namespace app\enum;

class GoodsEnum
{
    // 平台
    const PLATFORM_TAOBAO = 1;
    const PLATFORM_JD = 2;
    const PLATFORM_TMALL = 3;
    const PLATFORM_TMALL_CHAOSHI = 4;

    public static $platformArr = [
        self::PLATFORM_TAOBAO => '淘宝',
        self::PLATFORM_JD => '京东',
        self::PLATFORM_TMALL => '天猫',
        self::PLATFORM_TMALL_CHAOSHI => '天猫超市',
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

    // TTS情绪预设（通过 rate/pitch/volume 模拟）
    const EMOTION_DEFAULT    = 'default';
    const EMOTION_CHEERFUL   = 'cheerful';
    const EMOTION_EXCITED    = 'excited';
    const EMOTION_GENTLE     = 'gentle';
    const EMOTION_SAD        = 'sad';
    const EMOTION_SERIOUS    = 'serious';

    public static $emotionArr = [
        self::EMOTION_DEFAULT  => '默认',
        self::EMOTION_CHEERFUL => '开心',
        self::EMOTION_EXCITED  => '激昂',
        self::EMOTION_GENTLE   => '温柔',
        self::EMOTION_SAD      => '悲伤',
        self::EMOTION_SERIOUS  => '严肃',
    ];

    /** 情绪 → edge-tts 参数映射 */
    public static $emotionParamsMap = [
        self::EMOTION_DEFAULT  => ['rate' => '+0%',  'pitch' => '+0Hz',  'volume' => '+0%'],
        self::EMOTION_CHEERFUL => ['rate' => '+10%', 'pitch' => '+5Hz',  'volume' => '+5%'],
        self::EMOTION_EXCITED  => ['rate' => '+18%', 'pitch' => '+8Hz',  'volume' => '+15%'],
        self::EMOTION_GENTLE   => ['rate' => '-8%',  'pitch' => '-3Hz',  'volume' => '-10%'],
        self::EMOTION_SAD      => ['rate' => '-15%', 'pitch' => '-8Hz',  'volume' => '-5%'],
        self::EMOTION_SERIOUS  => ['rate' => '-5%',  'pitch' => '-5Hz',  'volume' => '+10%'],
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

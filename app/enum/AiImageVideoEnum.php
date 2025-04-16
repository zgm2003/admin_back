<?php

namespace app\enum;

class AiImageVideoEnum
{
    const TASK_DRAFT = 1;               // 草稿箱
    const TASK_PROMPT = 2;              // 提示词生成中
    const TASK_PROMPT_ERROR = 3;        // 提示词生成失败
    const TASK_PROMPT_SUCCESS = 4;      // 提示词生成成功

    public static $taskStatusArr = [
        self::TASK_DRAFT => "草稿箱",
        self::TASK_PROMPT => "提示词生成中",
        self::TASK_PROMPT_ERROR => "提示词生成失败",
        self::TASK_PROMPT_SUCCESS => "提示词生成成功",
    ];

    const DRAFT = 1;               // 草稿箱
    const IMAGE = 2;
    const IMAGE_ERROR = 3;
    const IMAGE_SUCCESS = 4;
    const VIDEO = 5;
    const VIDEO_ERROR = 6;
    const VIDEO_SUCCESS = 7;

    public static $statusArr = [
        self::DRAFT => "草稿箱",
        self::IMAGE => "图片生成中",
        self::IMAGE_ERROR => "图片生成失败",
        self::IMAGE_SUCCESS => "图片生成成功",
        self::VIDEO => "视频生成中",
        self::VIDEO_ERROR => "视频生成失败",
        self::VIDEO_SUCCESS => "视频生成成功",

    ];

    const PLATFORM_DOUYIN = 1;  // 抖音
    const PLATFORM_XIAOHONGSHU = 2;  // 小红书
    const PLATFORM_KUAISHOU = 3;  // 快手
    const PLATFORM_WECHAT = 4;  // 微信视频号
    const PLATFORM_BILIBILI = 5;  // 哔哩哔哩
    const PLATFORM_TAOBAO = 6;  // 淘宝直播
    const PLATFORM_PINDUODUO = 7;  // 拼多多
    const PLATFORM_JD = 8;  // 京东直播
    const PLATFORM_WEIBO = 9;  // 微博
    const PLATFORM_MEITUAN = 10; // 美团
    const PLATFORM_ALIPAY_LIFE = 11; // 支付宝生活号
    const PLATFORM_ZHIHU = 12; // 知乎
    const PLATFORM_TOUTIAO = 13; // 今日头条
    const PLATFORM_YOUKU = 14; // 优酷
    const PLATFORM_TENCENT_VIDEO = 15; // 腾讯视频
    const PLATFORM_IQIYI = 16; // 爱奇艺
    const PLATFORM_TENCENT_NEWS = 17; // 腾讯新闻
    const PLATFORM_DIANPING = 18; // 大众点评

    public static $platformArr = [
        self::PLATFORM_DOUYIN => "抖音",
        self::PLATFORM_XIAOHONGSHU => "小红书",
        self::PLATFORM_KUAISHOU => "快手",
        self::PLATFORM_WECHAT => "微信视频号",
        self::PLATFORM_BILIBILI => "哔哩哔哩",
        self::PLATFORM_TAOBAO => "淘宝直播",
        self::PLATFORM_PINDUODUO => "拼多多",
        self::PLATFORM_JD => "京东直播",
        self::PLATFORM_WEIBO => "微博",
        self::PLATFORM_MEITUAN => "美团",
        self::PLATFORM_ALIPAY_LIFE => "支付宝生活号",
        self::PLATFORM_ZHIHU => "知乎",
        self::PLATFORM_TOUTIAO => "今日头条",
        self::PLATFORM_YOUKU => "优酷",
        self::PLATFORM_TENCENT_VIDEO => "腾讯视频",
        self::PLATFORM_IQIYI => "爱奇艺",
        self::PLATFORM_TENCENT_NEWS => "腾讯新闻",
        self::PLATFORM_DIANPING => "大众点评",
    ];

    const IMAGE_SIZE_1024X1024 = 1;
    const IMAGE_SIZE_1024X2048 = 2;
    const IMAGE_SIZE_1536X1024 = 3;
    const IMAGE_SIZE_1536X2048 = 4;
    const IMAGE_SIZE_2048X1152 = 5;
    const IMAGE_SIZE_1152X2048 = 6;

    public static $imageSizeArr = [
        self::IMAGE_SIZE_1024X1024 => "1024x1024",
        self::IMAGE_SIZE_1024X2048 => "1024x2048",
        self::IMAGE_SIZE_1536X1024 => "1536x1024",
        self::IMAGE_SIZE_1536X2048 => "1536x2048",
        self::IMAGE_SIZE_2048X1152 => "2048x1152",
        self::IMAGE_SIZE_1152X2048 => "1152x2048",
    ];


}

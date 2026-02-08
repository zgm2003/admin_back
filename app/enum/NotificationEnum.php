<?php

namespace app\enum;

use app\enum\CommonEnum;

/**
 * 通知枚举
 */
class NotificationEnum
{
    // ==================== 通知类型 ====================
    const TYPE_INFO = 1;
    const TYPE_SUCCESS = 2;
    const TYPE_WARNING = 3;
    const TYPE_ERROR = 4;

    public static $typeArr = [
        self::TYPE_INFO => '普通',
        self::TYPE_SUCCESS => '成功',
        self::TYPE_WARNING => '警告',
        self::TYPE_ERROR => '错误',
    ];

    // 类型映射（TINYINT -> 字符串，用于前端显示和 WebSocket）
    public static $typeStrMap = [
        self::TYPE_INFO => 'info',
        self::TYPE_SUCCESS => 'success',
        self::TYPE_WARNING => 'warning',
        self::TYPE_ERROR => 'error',
    ];

    // ==================== 通知级别 ====================
    const LEVEL_NORMAL = 1;
    const LEVEL_URGENT = 2;

    public static $levelArr = [
        self::LEVEL_NORMAL => '普通',
        self::LEVEL_URGENT => '紧急',
    ];

    public static $levelStrMap = [
        self::LEVEL_NORMAL => 'normal',
        self::LEVEL_URGENT => 'urgent',
    ];

    // ==================== 任务目标类型 ====================
    const TARGET_ALL = 1;
    const TARGET_USERS = 2;
    const TARGET_ROLES = 3;

    public static $targetTypeArr = [
        self::TARGET_ALL => '全部用户',
        self::TARGET_USERS => '指定用户',
        self::TARGET_ROLES => '指定角色',
    ];

    // ==================== 任务状态 ====================
    const STATUS_PENDING = 1;
    const STATUS_SENDING = 2;
    const STATUS_SUCCESS = 3;
    const STATUS_FAILED = 4;

    public static $statusArr = [
        self::STATUS_PENDING => '待发送',
        self::STATUS_SENDING => '发送中',
        self::STATUS_SUCCESS => '已完成',
        self::STATUS_FAILED => '失败',
    ];

    // ==================== 已读状态 ====================
    public static $readStatusArr = [
        CommonEnum::YES => '已读',
        CommonEnum::NO => '未读',
    ];

    /**
     * 获取类型字符串
     */
    public static function getTypeStr(int $type): string
    {
        return self::$typeStrMap[$type] ?? 'info';
    }

    /**
     * 获取级别字符串
     */
    public static function getLevelStr(int $level): string
    {
        return self::$levelStrMap[$level] ?? 'normal';
    }
}

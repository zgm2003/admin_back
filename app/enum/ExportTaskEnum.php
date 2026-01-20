<?php

namespace app\enum;

/**
 * 导出任务枚举
 */
class ExportTaskEnum
{
    // 状态
    const STATUS_PENDING = 1;   // 处理中
    const STATUS_SUCCESS = 2;   // 成功
    const STATUS_FAILED = 3;    // 失败

    public static $statusArr = [
        self::STATUS_PENDING => '处理中',
        self::STATUS_SUCCESS => '已完成',
        self::STATUS_FAILED => '失败',
    ];
}

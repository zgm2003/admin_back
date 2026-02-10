<?php

namespace app\service\System;

use app\dep\System\SystemSettingDep;

/**
 * 系统设置服务 - 统一从数据库读取配置
 * 负责类型转换、业务逻辑，优先读数据库，fallback 到 config 文件
 */
class SettingService
{
    private static ?SystemSettingDep $dep = null;

    private static function dep(): SystemSettingDep
    {
        if (self::$dep === null) {
            self::$dep = new SystemSettingDep();
        }
        return self::$dep;
    }

    /**
     * 获取配置值（带类型转换，优先数据库，fallback config 文件）
     */
    public static function get(string $key, $default = null)
    {
        $raw = self::dep()->getRaw($key);
        if ($raw === null) {
            return $default;
        }

        return self::convertType($raw['setting_value'], $raw['value_type'], $default);
    }

    /**
     * 设置配置值（带类型转换）
     */
    public static function set(string $key, $value, int $type = 1, string $remark = ''): bool
    {
        // 类型转换：将值转为字符串存储
        $strValue = match ($type) {
            4 => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            3 => $value ? '1' : '0',
            default => (string)$value,
        };

        return self::dep()->setRaw($key, $strValue, $type, $remark);
    }

    /**
     * 类型转换（业务逻辑）
     */
    private static function convertType(string $value, int $type, $default)
    {
        return match ($type) {
            2 => is_numeric($value) ? $value + 0 : $default, // 数字
            3 => in_array(strtolower($value), ['1', 'true'], true), // 布尔
            4 => json_decode($value, true) ?? $default, // JSON
            default => $value, // 字符串
        };
    }

    // ==================== User 相关 ====================

    public static function getDefaultAvatar(): string
    {
        return (string)self::get('user.default_avatar', '');
    }

    public static function isRegisterEnabled(): bool
    {
        return (bool)self::get('user.register_enabled', true);
    }
}

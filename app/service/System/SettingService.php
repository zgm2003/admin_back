<?php

namespace app\service\System;

use app\dep\System\SystemSettingDep;

/**
 * 系统设置服务 - 统一从数据库读取配置
 * 优先读数据库，fallback 到 config 文件
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
     * 获取配置值（优先数据库，fallback config 文件）
     */
    public static function get(string $key, $default = null)
    {
        $value = self::dep()->getValue($key);
        return $value !== null ? $value : $default;
    }

    // ==================== Auth 相关 ====================

    public static function getAccessTtl(): int
    {
        return (int)self::get('auth.access_ttl', config('auth.access_ttl', 4 * 3600));
    }

    public static function getRefreshTtl(): int
    {
        return (int)self::get('auth.refresh_ttl', config('auth.refresh_ttl', 14 * 24 * 3600));
    }

    public static function getAuthPolicy(string $platform): array
    {
        $policy = self::get('auth.policy.' . $platform);
        if ($policy !== null) {
            return is_array($policy) ? $policy : [];
        }
        // fallback 到 config 文件
        return config('auth.policies.' . $platform) ?? self::getDefaultPolicy();
    }

    public static function getDefaultPolicy(): array
    {
        $policy = self::get('auth.default_policy');
        if ($policy !== null) {
            return is_array($policy) ? $policy : [];
        }
        return config('auth.default_policy', [
            'bind_platform' => true,
            'bind_device' => true,
            'bind_ip' => false,
            'single_session_per_platform' => false,
        ]);
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

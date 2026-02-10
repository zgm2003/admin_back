<?php

namespace app\service\System;

use app\dep\System\AuthPlatformDep;
use app\enum\CommonEnum;
use app\exception\BusinessException;

/**
 * 认证平台服务 — 统一对外提供平台配置
 *
 * 三级缓存架构：进程内存（0ms）→ Redis（0.1-0.5ms）→ MySQL（1-5ms）
 * 内存缓存 TTL 60秒，多 Worker 间最大延迟 60秒，对平台配置可接受
 */
class AuthPlatformService
{
    private static ?AuthPlatformDep $dep = null;

    /** 进程级内存缓存：code → 平台数据 */
    private static array $memPlatform = [];
    /** 进程级内存缓存：所有启用平台 code 列表 */
    private static ?array $memCodes = null;
    /** 进程级内存缓存：code→name 映射 */
    private static ?array $memMap = null;
    /** 内存缓存写入时间戳 */
    private static int $memPlatformAt = 0;
    private static int $memCodesAt = 0;
    private static int $memMapAt = 0;

    /** 内存缓存 TTL（秒） */
    private const MEM_TTL = 60;

    private static function dep(): AuthPlatformDep
    {
        if (self::$dep === null) {
            self::$dep = new AuthPlatformDep();
        }
        return self::$dep;
    }

    /**
     * 清除当前进程的内存缓存（写操作后调用）
     */
    public static function flushMemCache(): void
    {
        self::$memPlatform = [];
        self::$memCodes = null;
        self::$memMap = null;
        self::$memPlatformAt = 0;
        self::$memCodesAt = 0;
        self::$memMapAt = 0;
    }

    /**
     * 内存缓存是否过期
     */
    private static function isExpired(int $timestamp): bool
    {
        return (\time() - $timestamp) > self::MEM_TTL;
    }

    // ==================== 核心查询方法 ====================

    /**
     * 获取平台配置（fail-close：未配置或禁用则拒绝）
     * 三级缓存：内存 → Redis → DB
     * @throws BusinessException
     */
    public static function getPlatform(string $code): array
    {
        // L1: 进程内存
        if (isset(self::$memPlatform[$code]) && !self::isExpired(self::$memPlatformAt)) {
            return self::$memPlatform[$code];
        }

        // L2+L3: Redis → DB（由 Dep 层处理）
        $platform = self::dep()->getByCode($code);
        if (!$platform) {
            throw new BusinessException("平台 [{$code}] 未配置或已禁用，拒绝访问", 401);
        }

        // 回写内存
        self::$memPlatform[$code] = $platform;
        self::$memPlatformAt = \time();

        return $platform;
    }

    /**
     * 获取所有启用的平台 code 列表
     */
    public static function getAllowedPlatforms(): array
    {
        if (self::$memCodes !== null && !self::isExpired(self::$memCodesAt)) {
            return self::$memCodes;
        }

        self::$memCodes = self::dep()->getAllActiveCodes();
        self::$memCodesAt = \time();
        return self::$memCodes;
    }

    /**
     * 获取所有启用的平台 code→name 映射
     */
    public static function getPlatformMap(): array
    {
        if (self::$memMap !== null && !self::isExpired(self::$memMapAt)) {
            return self::$memMap;
        }

        self::$memMap = self::dep()->getAllActiveMap();
        self::$memMapAt = \time();
        return self::$memMap;
    }

    // ==================== 便捷方法（均基于 getPlatform 的内存缓存） ====================

    /**
     * 校验平台是否合法并返回安全策略（合并调用，一次查询搞定）
     * 用于 CheckToken 中间件，替代 isValidPlatform + getAuthPolicy 两次调用
     */
    public static function validateAndGetPolicy(string $code): ?array
    {
        if (!\in_array($code, self::getAllowedPlatforms(), true)) {
            return null;
        }
        return self::getAuthPolicy($code);
    }

    /**
     * 校验平台是否合法
     */
    public static function isValidPlatform(string $code): bool
    {
        return \in_array($code, self::getAllowedPlatforms(), true);
    }

    /**
     * 获取平台的完整安全策略
     */
    public static function getAuthPolicy(string $code): array
    {
        $p = self::getPlatform($code);
        return [
            'bind_platform'              => $p['bind_platform'] === CommonEnum::YES,
            'bind_device'                => $p['bind_device'] === CommonEnum::YES,
            'bind_ip'                    => $p['bind_ip'] === CommonEnum::YES,
            'single_session_per_platform' => $p['single_session'] === CommonEnum::YES,
            'max_sessions'               => (int)$p['max_sessions'],
            'allow_register'             => $p['allow_register'] === CommonEnum::YES,
        ];
    }

    /**
     * 获取平台的 access_token TTL
     */
    public static function getAccessTtl(string $code): int
    {
        return (int)self::getPlatform($code)['access_ttl'];
    }

    /**
     * 获取平台的 refresh_token TTL
     */
    public static function getRefreshTtl(string $code): int
    {
        return (int)self::getPlatform($code)['refresh_ttl'];
    }

    /**
     * 获取平台允许的登录方式
     */
    public static function getLoginTypes(string $code): array
    {
        $p = self::getPlatform($code);
        $types = $p['login_types'];
        return \is_array($types) ? $types : \json_decode($types, true) ?? [];
    }

    /**
     * 平台是否允许注册
     */
    public static function isRegisterEnabled(string $code): bool
    {
        return self::getPlatform($code)['allow_register'] === CommonEnum::YES;
    }

    /**
     * 获取平台名称（code→name，未找到返回 code 本身）
     */
    public static function getPlatformName(string $code): string
    {
        $map = self::getPlatformMap();
        return $map[$code] ?? $code;
    }
}

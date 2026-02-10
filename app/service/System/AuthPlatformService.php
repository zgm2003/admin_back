<?php

namespace app\service\System;

use app\dep\System\AuthPlatformDep;
use app\enum\CommonEnum;
use app\exception\BusinessException;

/**
 * 认证平台服务 — 统一对外提供平台配置
 * 替代原来散落在 PermissionEnum + SettingService 中的硬编码逻辑
 */
class AuthPlatformService
{
    private static ?AuthPlatformDep $dep = null;

    private static function dep(): AuthPlatformDep
    {
        if (self::$dep === null) {
            self::$dep = new AuthPlatformDep();
        }
        return self::$dep;
    }

    /**
     * 获取平台配置（fail-close：未配置或禁用则拒绝）
     * @throws BusinessException
     */
    public static function getPlatform(string $code): array
    {
        $platform = self::dep()->getByCode($code);
        if (!$platform) {
            throw new BusinessException("平台 [{$code}] 未配置或已禁用，拒绝访问", 401);
        }
        return $platform;
    }

    /**
     * 获取所有启用的平台 code 列表
     */
    public static function getAllowedPlatforms(): array
    {
        return self::dep()->getAllActiveCodes();
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

    /**
     * 获取所有启用的平台 code→name 映射
     */
    public static function getPlatformMap(): array
    {
        return self::dep()->getAllActiveMap();
    }
}

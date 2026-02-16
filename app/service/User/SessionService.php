<?php

namespace app\service\User;

use app\dep\User\UserSessionsDep;
use app\enum\CacheTTLEnum;
use app\enum\CommonEnum;
use app\exception\BusinessException;
use app\service\System\AuthPlatformService;
use Carbon\Carbon;
use support\Redis;

/**
 * 会话服务
 * 统一管理会话的创建、刷新、撤销、淘汰策略、指针维护
 */
class SessionService
{
    private static ?UserSessionsDep $sessionDep = null;

    private static function dep(): UserSessionsDep
    {
        if (self::$sessionDep === null) {
            self::$sessionDep = new UserSessionsDep();
        }
        return self::$sessionDep;
    }

    /**
     * 创建会话（含淘汰策略）
     */
    public static function create(int $userId, string $platform, string $deviceId, string $ip, string $ua): array
    {
        $tokens = TokenService::generateTokenPair($platform);
        $policy = AuthPlatformService::getAuthPolicy($platform);

        // 会话淘汰
        self::evict($userId, $platform, $policy);

        $sessionId = self::dep()->add([
            'user_id'            => $userId,
            'access_token_hash'  => $tokens['access_token_hash'],
            'refresh_token_hash' => $tokens['refresh_token_hash'],
            'platform'           => $platform,
            'device_id'          => $deviceId,
            'ip'                 => $ip,
            'ua'                 => $ua,
            'expires_at'         => $tokens['access_expires']->toDateTimeString(),
            'refresh_expires_at' => $tokens['refresh_expires']->toDateTimeString(),
            'last_seen_at'       => $tokens['now']->toDateTimeString(),
            'is_del'             => CommonEnum::NO,
        ]);

        self::updatePointer($userId, $platform, $sessionId);

        return [
            'access_token'     => $tokens['access_token'],
            'refresh_token'    => $tokens['refresh_token'],
            'expires_in'       => $tokens['access_ttl'],
            'refresh_expires_in' => $tokens['refresh_ttl'],
        ];
    }

    /**
     * 刷新会话
     * @throws BusinessException
     */
    public static function refresh(string $refreshToken, string $ip, string $ua): array
    {
        try {
            $hash = TokenService::hashToken($refreshToken);
        } catch (\Exception $e) {
            throw new BusinessException('令牌格式错误', 401);
        }

        $session = self::dep()->findValidByRefreshHash($hash);
        if (!$session) {
            throw new BusinessException('刷新令牌无效或已过期', 401);
        }

        if (Carbon::parse($session['refresh_expires_at'])->isPast()) {
            throw new BusinessException('刷新令牌已过期，请重新登录', 401);
        }

        $platform = $session['platform'];

        // 单端登录检查
        if (!self::checkSingleSession($session['user_id'], $platform, $session['id'])) {
            throw new BusinessException('账号已在其他设备登录，请重新登录', 401);
        }

        $tokens = TokenService::generateTokenPair($platform);

        self::dep()->rotate($session['id'], [
            'access_token_hash'  => $tokens['access_token_hash'],
            'refresh_token_hash' => $tokens['refresh_token_hash'],
            'expires_at'         => $tokens['access_expires']->toDateTimeString(),
            'refresh_expires_at' => $session['refresh_expires_at'],
            'last_seen_at'       => $tokens['now']->toDateTimeString(),
            'ip'                 => $ip,
            'ua'                 => $ua,
        ]);

        // 清理旧 token 缓存
        if (!empty($session['access_token_hash'])) {
            Redis::connection('token')->del($session['access_token_hash']);
        }

        self::updatePointer($session['user_id'], $platform, $session['id']);

        return [
            'access_token'       => $tokens['access_token'],
            'refresh_token'      => $tokens['refresh_token'],
            'expires_in'         => $tokens['access_ttl'],
            'refresh_expires_in' => $tokens['refresh_ttl'],
        ];
    }

    /**
     * 撤销会话（登出）
     */
    public static function revoke(string $bearer): void
    {
        try {
            $token = str_replace('Bearer ', '', $bearer);
            $hash = TokenService::hashToken($token);
            $session = self::dep()->findValidByAccessHash($hash);

            if ($session) {
                self::dep()->revoke($session['id']);
                Redis::connection('token')->del($hash);
                self::clearPointerIfMatches($session['user_id'], $session['platform'], $session['id']);
            }
        } catch (\Exception $e) {
            // 登出容错，不抛异常
        }
    }

    /**
     * 会话淘汰策略
     */
    private static function evict(int $userId, string $platform, array $policy): void
    {
        if (!empty($policy['single_session_per_platform'])) {
            // 单端登录：踢掉该用户在此平台的所有旧会话
            $oldSessions = self::dep()->listActiveByUserPlatform($userId, $platform);
            // 先 DB 撤销，再清 Redis（保证一致性）
            self::dep()->revokeByUserPlatform($userId, $platform);
            foreach ($oldSessions as $old) {
                Redis::connection('token')->del($old->access_token_hash);
            }
        } elseif ($policy['max_sessions'] > 0) {
            // 多会话上限：FIFO 淘汰最早的超额会话
            $activeSessions = self::dep()->listActiveByUserPlatform($userId, $platform);
            $overCount = $activeSessions->count() - $policy['max_sessions'] + 1;
            if ($overCount > 0) {
                $toRevoke = $activeSessions->sortBy('id')->take($overCount);
                foreach ($toRevoke as $old) {
                    self::dep()->revoke($old->id);
                    Redis::connection('token')->del($old->access_token_hash);
                }
            }
        }
    }

    /**
     * 单端登录检查
     */
    public static function checkSingleSession(int $userId, string $platform, int $currentSessionId): bool
    {
        $policy = AuthPlatformService::getAuthPolicy($platform);
        if (empty($policy['single_session_per_platform'])) {
            return true;
        }

        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        $allowedId = Redis::connection('token')->get($key);

        if (!$allowedId) {
            $latest = self::dep()->findLatestActiveByUserPlatform($userId, $platform);
            if ($latest) {
                $allowedId = $latest->id;
                Redis::connection('token')->set($key, $allowedId, CacheTTLEnum::SINGLE_SESSION_POINTER);
            }
        }

        return (!$allowedId || (int)$allowedId === (int)$currentSessionId);
    }

    /**
     * 更新会话指针
     */
    public static function updatePointer(int $userId, string $platform, int $sessionId): void
    {
        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        Redis::connection('token')->set($key, $sessionId, CacheTTLEnum::SINGLE_SESSION_POINTER);
    }

    /**
     * 清除会话指针（仅当匹配时）
     */
    private static function clearPointerIfMatches(int $userId, string $platform, int $sessionId): void
    {
        if (!$platform) return;
        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        $currentPtr = Redis::connection('token')->get($key);
        if ($currentPtr && (int)$currentPtr === (int)$sessionId) {
            Redis::connection('token')->del($key);
        }
    }
}

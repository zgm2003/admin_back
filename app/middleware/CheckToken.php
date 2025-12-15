<?php

namespace app\middleware;

use app\dep\User\UsersDep;
use app\dep\User\UsersTokenDep;
use app\enum\ErrorCodeEnum;
use Carbon\Carbon;
use support\Request;
use Webman\Http\Response;
use support\Redis;

class CheckToken
{
    // Redis TTL
    const REDIS_TTL = 300; // 缓存 5 分钟

    public function process(Request $request, callable $next): Response
    {
        $bearer = $request->header('authorization');
        if (!$bearer) {
            return json([
                'code' => ErrorCodeEnum::UNAUTHORIZED,
                'data' => [],
                'msg'  => '缺少Token',
            ]);
        }
        $token = str_replace('Bearer ', '', $bearer);

        // 1. 从 token 专用 Redis 连接读取（Redis 配置中的 prefix 会自动添加）
        $cached = Redis::connection('token')->get($token);
        if ($cached) {
            $parts = explode('|', $cached);
            $userId = $parts[0] ?? null;
            $expiresAt = Carbon::parse($parts[1] ?? null);
            $lastIp = $parts[2] ?? null;
            $cachedPlatform = $parts[3] ?? null;
        } else {
            // 2. 缓存未命中，回落到数据库
            $tokenDep = new UsersTokenDep();
            $row = $tokenDep->firstByToken($token);
            if (!$row) {
                return json([
                    'code' => ErrorCodeEnum::UNAUTHORIZED,
                    'data' => [],
                    'msg'  => 'Token无效或用户不存在',
                ]);
            }
            $userId = $row->user_id;
            $expiresAt = Carbon::parse($row->expires_in);
            $lastIp = $row->ip;
            $cachedPlatform = $row->platform ?? null;
            $headerPlatform = $request->header('platform');
            if ($headerPlatform && (empty($cachedPlatform))) {
                (new UsersTokenDep())->editByToken($token, ['platform' => $headerPlatform]);
                $cachedPlatform = $headerPlatform;
            }

            // 写入 Redis（只用 token 作为 key，prefix 由配置自动加）
            $value = implode('|', [
                $userId,
                $expiresAt->toDateTimeString(),
                $lastIp ?? '',
                $cachedPlatform ?? ''
            ]);
            Redis::connection('token')->set($token, $value, self::REDIS_TTL);
        }

        // 3. 检查过期
        if ($expiresAt->isPast()) {
            Redis::connection('token')->del($token);
            return json([
                'code' => ErrorCodeEnum::UNAUTHORIZED,
                'data' => [],
                'msg'  => 'Token已过期',
            ]);
        }

        // 4. IP 绑定校验
        $currentIp = $request->getRealIp();
        if (!empty($lastIp) && $lastIp !== $currentIp) {
            (new UsersTokenDep())->clearIpByToken($token);
            Redis::connection('token')->del($token);
            $this->log("IP 地址不一致：期望 {$lastIp}，实际 {$currentIp}，Token={$token}");
            return json([
                'code' => ErrorCodeEnum::UNAUTHORIZED,
                'data' => [],
                'msg'  => 'IP地址不匹配，请重新登录',
            ]);
        }

        $platformHeader = $request->header('platform');
        if ($platformHeader && isset($cachedPlatform) && $cachedPlatform && strtolower($platformHeader) !== strtolower($cachedPlatform)) {
            (new UsersTokenDep())->clearIpByToken($token);
            Redis::connection('token')->del($token);
            return json([
                'code' => ErrorCodeEnum::UNAUTHORIZED,
                'data' => [],
                'msg'  => '平台不匹配，请重新登录',
            ]);
        }

        // 5. 认证通过，绑定用户并续期缓存
//        $user = (new UsersDep())->first($userId);
//        $request->setUser($user);
        $request->userId = $userId;
        Redis::connection('token')->expire($token, self::REDIS_TTL);

        return $next($request);
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily('checkToken');
        $logger->info($msg, $context);
    }
}


<?php

namespace app\middleware;

use app\dep\User\UserSessionsDep;
use app\enum\ErrorCodeEnum;
use app\enum\CacheTTLEnum;
use app\service\System\AuthPlatformService;
use app\service\User\TokenService;
use Carbon\Carbon;
use support\Redis;
use support\Request;
use Webman\Http\Response;

class CheckToken
{
    public function process(Request $request, callable $next): Response
    {
        // 1. 获取并解析 Token
        $bearer = $request->header('authorization');
        if (!$bearer) {
            return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'data' => [], 'msg' => '缺少Token']);
        }

        $token = str_replace('Bearer ', '', $bearer);

        try {
            $tokenHash = TokenService::hashToken($token);
        } catch (\Exception $e) {
            return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'data' => [], 'msg' => 'Token格式错误']);
        }

        // 2. 查询会话（Redis 缓存 → DB 回查）
        $redisKey = $tokenHash;
        $session = $this->resolveSession($redisKey, $tokenHash);

        if (!$session) {
            return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'data' => [], 'msg' => 'Token无效或已过期']);
        }

        // 3. 检查过期
        if (Carbon::parse($session['expires_at'])->isPast()) {
            Redis::connection('token')->del($redisKey);
            return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'data' => [], 'msg' => 'Token已过期']);
        }

        // 4. 平台校验 + 安全策略（合并调用，减少缓存查询）
        $currentPlatform = $request->header('platform');
        if (!$currentPlatform || !AuthPlatformService::isValidPlatform($currentPlatform)) {
            return json(['code' => ErrorCodeEnum::PARAM_ERROR, 'msg' => '无效的平台标识', 'data' => []]);
        }

        // 5. 安全策略（getPlatform 已被内存缓存，不再产生额外 Redis 查询）
        $policy = AuthPlatformService::getAuthPolicy($session['platform']);

        // 5.1 绑定平台
        if (!empty($policy['bind_platform'])) {
            if (strtolower($session['platform']) !== strtolower($currentPlatform)) {
                return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'msg' => '平台不匹配', 'data' => []]);
            }
        }

        // 5.2 绑定设备
        if (!empty($policy['bind_device']) && !empty($session['device_id'])) {
            $currentDevice = $request->header('device-id');
            if (!$currentDevice || $currentDevice !== $session['device_id']) {
                return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'msg' => '设备变更，请重新登录', 'data' => []]);
            }
        }

        // 5.3 绑定 IP
        if (!empty($policy['bind_ip'])) {
            if ($session['ip'] !== $request->getRealIp()) {
                Redis::connection('token')->del($redisKey);
                return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'msg' => 'IP地址变动', 'data' => []]);
            }
        }

        // 6. 挂载请求信息
        $request->userId = (int)$session['user_id'];
        $request->sessionId = (int)$session['id'];
        $request->platform = $session['platform'];

        // 7. 单端登录策略
        if (!empty($policy['single_session_per_platform'])) {
            $result = $this->checkSingleSession($session, $redisKey);
            if ($result) {
                return $result;
            }
        }

        // 8. 续期 Redis 缓存
        Redis::connection('token')->expire($redisKey, CacheTTLEnum::TOKEN_SESSION);

        return $next($request);
    }

    /**
     * 解析会话：优先 Redis 缓存，未命中则查 DB 并回写
     */
    private function resolveSession(string $redisKey, string $tokenHash): ?array
    {
        $cached = Redis::connection('token')->get($redisKey);

        if ($cached) {
            $parts = explode('|', $cached);
            if (\count($parts) >= 4) {
                return [
                    'user_id'    => $parts[0],
                    'expires_at' => $parts[1],
                    'ip'         => $parts[2],
                    'platform'   => $parts[3],
                    'device_id'  => $parts[4] ?? '',
                    'id'         => $parts[5] ?? 0,
                ];
            }
        }

        $sessionDep = new UserSessionsDep();
        $row = $sessionDep->findValidByAccessHash($tokenHash);
        if (!$row) {
            return null;
        }

        $session = \is_object($row) ? $row->toArray() : (array)$row;

        // 回写 Redis
        $value = implode('|', [
            $session['user_id'],
            $session['expires_at'],
            $session['ip'] ?? '',
            $session['platform'] ?? '',
            $session['device_id'] ?? '',
            $session['id'],
        ]);
        Redis::connection('token')->set($redisKey, $value, CacheTTLEnum::TOKEN_SESSION);

        return $session;
    }

    /**
     * 单端登录裁决：通过 Redis 指针判断当前会话是否为允许的会话
     */
    private function checkSingleSession(array $session, string $redisKey): ?Response
    {
        $curSessKey = "cur_sess:" . strtolower(trim($session['platform'])) . ":{$session['user_id']}";
        $allowedSessionId = Redis::connection('token')->get($curSessKey);

        // 指针不存在，从 DB 重建
        if (!$allowedSessionId) {
            $latest = (new UserSessionsDep())->findLatestActiveByUserPlatform($session['user_id'], $session['platform']);
            if ($latest) {
                $allowedSessionId = $latest->id;
                Redis::connection('token')->set($curSessKey, $allowedSessionId, CacheTTLEnum::SINGLE_SESSION_POINTER);
            }
        }
        // 指针存在但裁决失败时，验证指针有效性（仅此时查库，避免每次请求都查）
        elseif ((int)$allowedSessionId !== (int)$session['id']) {
            $latest = (new UserSessionsDep())->findLatestActiveByUserPlatform($session['user_id'], $session['platform']);
            if ($latest && $latest->id != $allowedSessionId) {
                $allowedSessionId = $latest->id;
                Redis::connection('token')->set($curSessKey, $allowedSessionId, CacheTTLEnum::SINGLE_SESSION_POINTER);
            } elseif (!$latest) {
                $allowedSessionId = null;
            }
        }

        // 裁决：当前会话不是允许的会话，踢下线
        if ($allowedSessionId && (int)$allowedSessionId !== (int)$session['id']) {
            Redis::connection('token')->del($redisKey);
            return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'msg' => '账号已在其他设备登录', 'data' => []]);
        }

        return null;
    }
}

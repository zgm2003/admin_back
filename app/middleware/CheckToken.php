<?php

namespace app\middleware;

use app\dep\User\UserSessionsDep;
use app\enum\ErrorCodeEnum;
use app\enum\PermissionEnum;
use app\enum\CacheTTLEnum;
use app\service\System\SettingService;
use app\service\User\TokenService;
use Carbon\Carbon;
use support\Redis;
use support\Request;
use Webman\Http\Response;

class CheckToken
{
    public function process(Request $request, callable $next): Response
    {
        // 1. 获取 Token
        $bearer = $request->header('authorization');
        // 1.1 检查是否携带 Token
        if (!$bearer) {
            return json([
                'code' => ErrorCodeEnum::UNAUTHORIZED,
                'data' => [],
                'msg'  => '缺少Token',
            ]);
        }
        // 1.2 解析 Token
        $token = str_replace('Bearer ', '', $bearer);

        // 1.3 验证 Token 格式
        try {
            $tokenHash = TokenService::hashToken($token);
        } catch (\Exception $e) {
            return json([
                'code' => ErrorCodeEnum::UNAUTHORIZED,
                'data' => [],
                'msg'  => 'Token格式错误',
            ]);
        }

        // 2. 从 Redis 读取缓存
        $redisKey = $tokenHash;

        // 2.1 从 Redis 读取缓存
        $cached = Redis::connection('token')->get($redisKey);
        
        $session = null;

        if ($cached) {
            $parts = explode('|', $cached);
            // Format: userId|expiresAt|ip|platform|deviceId|sessionId
            if (count($parts) >= 4) {
                $session = [
                    'user_id' => $parts[0],
                    'expires_at' => $parts[1],
                    'ip' => $parts[2],
                    'platform' => $parts[3],
                    'device_id' => $parts[4] ?? '',
                    'id' => $parts[5] ?? 0,
                ];
            }
        }

        if (!$session) {
            // 2.2 缓存未命中，查库
            $sessionDep = new UserSessionsDep();
            $row = $sessionDep->findValidByAccessHash($tokenHash);
            
            if (!$row) {
                 return json([
                    'code' => ErrorCodeEnum::UNAUTHORIZED,
                    'data' => [],
                    'msg'  => 'Token无效或已过期',
                ]);
            }
            
            // Convert model/object to array
            $session = is_object($row) ? $row->toArray() : (array)$row;
            
            // 2.3 写入 Redis
            $value = implode('|', [
                $session['user_id'],
                $session['expires_at'],
                $session['ip'] ?? '',
                $session['platform'] ?? '',
                $session['device_id'] ?? '',
                $session['id']
            ]);
            Redis::connection('token')->set($redisKey, $value, CacheTTLEnum::TOKEN_SESSION);
        }

        // 3.1  检查过期
        if (Carbon::parse($session['expires_at'])->isPast()) {
             Redis::connection('token')->del($redisKey);
             return json([
                'code' => ErrorCodeEnum::UNAUTHORIZED,
                'data' => [],
                'msg'  => 'Token已过期',
            ]);
        }

        // 4.1 平台强制校验（必须为 admin 或 app）
        $currentPlatform = $request->header('platform');
        if (!$currentPlatform || !in_array($currentPlatform, PermissionEnum::ALLOWED_PLATFORMS, true)) {
            return json([
                'code' => ErrorCodeEnum::PARAM_ERROR,
                'msg'  => '无效的平台标识，必须为 admin 或 app',
                'data' => []
            ]);
        }
        
        // 4.2 Load policy
        $policyConfig = SettingService::getAuthPolicy($session['platform']);
        
        // 4.3 Bind Platform
        if (!empty($policyConfig['bind_platform'])) {
             if (!$currentPlatform || strtolower($session['platform']) !== strtolower($currentPlatform)) {
                 return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'msg' => '平台不匹配', 'data' => []]);
             }
        }
        
        // 4.4 Bind Device
        $currentDevice = $request->header('device-id');
        if (!empty($policyConfig['bind_device']) && !empty($session['device_id'])) {
             if (!$currentDevice || $currentDevice !== $session['device_id']) {
                 return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'msg' => '设备变更，请重新登录', 'data' => []]);
             }
        }
        
        // 4.5 Bind IP
        $currentIp = $request->getRealIp();
        if (!empty($policyConfig['bind_ip'])) {
             if ($session['ip'] !== $currentIp) {
                 Redis::connection('token')->del($redisKey);
                 return json(['code' => ErrorCodeEnum::UNAUTHORIZED, 'msg' => 'IP地址变动', 'data' => []]);
             }
        }

        // 5.1 挂载信息
        $request->userId = (int)$session['user_id'];
        $request->sessionId = (int)$session['id'];
        $request->platform = $session['platform'];

        // 6.2 🛡️ single_session_per_platform 策略（立刻生效）
        if (!empty($policyConfig['single_session_per_platform'])) {
            $curSessKey = "cur_sess:" . strtolower(trim($session['platform'])) . ":{$session['user_id']}";
            
            // 6.2.1 读取 Redis 指针
            $allowedSessionId = Redis::connection('token')->get($curSessKey);

            // 6.2.2 如果指针不存在，从 DB 重建
            if (!$allowedSessionId) {
                $latest = (new UserSessionsDep())->findLatestActiveByUserPlatform($session['user_id'], $session['platform']);
                if ($latest) {
                    $allowedSessionId = $latest->id;
                    // 写回 Redis（持久化，TTL 30 天）
                    Redis::connection('token')->set($curSessKey, $allowedSessionId, CacheTTLEnum::SINGLE_SESSION_POINTER);
                }
            }
            // 6.2.3 补丁 3：如果指针存在，但 DB 中该会话已无效，需重新计算指针
            else {
                // 优化：仅当裁决失败时才去验证指针的有效性，避免每次请求都查库
                if ((int)$allowedSessionId !== (int)$session['id']) {
                     // 此时 allowedSessionId != currentSessionId，准备踢人
                     // 但踢人前，先确认 allowedSessionId 指向的那个会话是否还健在
                     // 如果那个“天选之子”其实已经挂了（比如被管理员删了），那当前这个会话如果不重建指针就被冤枉了
                     
                     // 查 DB 确认 allowedSessionId 的状态
                     // 简便做法：直接重新计算一次最新会话，看看是不是我
                     $latest = (new UserSessionsDep())->findLatestActiveByUserPlatform($session['user_id'], $session['platform']);
                     if ($latest) {
                         $realLatestId = $latest->id;
                         // 如果真正的最新会话 ID 变了（比如原来的 allowedSessionId 过期了），更新指针
                         if ($realLatestId != $allowedSessionId) {
                             $allowedSessionId = $realLatestId;
                             // 指针修正，顺便续期
                             Redis::connection('token')->set($curSessKey, $allowedSessionId, CacheTTLEnum::SINGLE_SESSION_POINTER);
                         }
                     } else {
                         // 没有任何有效会话了？那我也得死
                         $allowedSessionId = null;
                     }
                }
            }

            // 6.3.1 裁决：如果当前 sessionId 不等于允许的 sessionId，则踢下线
            if ($allowedSessionId && (int)$allowedSessionId !== (int)$session['id']) {
                // 6.3.1.1 可选：顺便把这个非法的 session 在 DB 标记为 revoke，减少后续无意义查询
                // (new UserSessionsDep())->revokeById($session['id']);
                
                // 6.3.1.2 清除当前 token 的缓存，确保下次直接被拦截
                Redis::connection('token')->del($redisKey);

                return json([
                    'code' => ErrorCodeEnum::UNAUTHORIZED,
                    'msg'  => '账号已在其他设备登录', // 6.3.1.3 提示信息，当前会话被踢下线
                    'data' => []
                ]);
            }
        }
        
        // 6.4 续期 Redis 缓存
        Redis::connection('token')->expire($redisKey, CacheTTLEnum::TOKEN_SESSION);

        return $next($request);
    }
}

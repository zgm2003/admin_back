<?php
namespace app\middleware;

use app\dep\User\UsersDep;
use app\dep\User\UsersTokenDep;
use Carbon\Carbon;
use support\Request;
use Webman\Http\Response;

class CheckToken
{
    public function process(Request $request, callable $next): Response
    {
        $token = $request->header('authorization');
        if (!$token) {
            return response(json_encode(['message' => '缺少Token']),401);
        }
        $token = str_replace('Bearer ', '', $token);

        $userDep = new UsersDep();
        $tokenDep = new UsersTokenDep();
        $resDep = $tokenDep->firstByToken($token);

        if (!$resDep) {
            return response(json_encode(['message' => 'Token无效或用户不存在']),401);
        }

        $accessToken = $resDep->token;
        $expiresAt = Carbon::parse($resDep->expires_in);
        $lastIp = $resDep->ip;
        $currentIp = $request->getRealIp();

        if (!$accessToken || $expiresAt->isPast()) {
            return response(json_encode(['message' => 'Token已过期或Token不存在']),401);
        }

        if (!empty($lastIp) && $lastIp !== $currentIp) {
            // IP 不一致，清空数据库 IP
            $tokenDep->clearIpByToken($token);

            $this->log("IP 地址不一致：期望 {$lastIp}，实际 {$currentIp}，Token={$token}");

            return response(json_encode(['message' => 'IP地址不匹配，请重新登录']),401);
        }

        // 绑定用户信息到 request
        $user = $userDep->first($resDep['user_id']);
        $request->setUser($user);

        return $next($request);
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("checkToken"); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
}

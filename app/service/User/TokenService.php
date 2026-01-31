<?php

namespace app\service\User;

use app\service\System\SettingService;
use Carbon\Carbon;

class TokenService
{
    public static function makeToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function hashToken(string $token): string
    {
        $pepper = (string) config('app.token_pepper', '');
        if ($pepper === '' || $pepper === 'change_me_to_long_random') {
            throw new \RuntimeException('TOKEN_PEPPER 未配置或不安全，请在 .env 设置随机值');
        }
        return hash('sha256', $token . '|' . $pepper);
    }

    public static function generateTokenPair(): array
    {
        $now = Carbon::now();
        $accessTtl = SettingService::getAccessTtl();
        $refreshTtl = SettingService::getRefreshTtl();

        $accessToken = self::makeToken(32);
        $refreshToken = self::makeToken(64);

        return [
            'access_token'       => $accessToken,
            'refresh_token'      => $refreshToken,
            'access_token_hash'  => self::hashToken($accessToken),
            'refresh_token_hash' => self::hashToken($refreshToken),
            'access_expires'     => $now->copy()->addSeconds($accessTtl),
            'refresh_expires'    => $now->copy()->addSeconds($refreshTtl),
            'access_ttl'         => $accessTtl,
            'refresh_ttl'        => $refreshTtl,
            'now'                => $now,
        ];
    }
}


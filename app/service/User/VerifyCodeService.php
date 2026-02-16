<?php

namespace app\service\User;

use app\enum\CacheTTLEnum;
use app\enum\EmailEnum;
use app\exception\BusinessException;
use support\Cache;

/**
 * 验证码服务
 * 统一管理验证码的发送、校验、缓存 key 生成
 * scene 绑定到 key 中，防止跨场景使用
 */
class VerifyCodeService
{
    /**
     * 生成缓存 key（绑定 scene，防止跨场景使用）
     */
    public static function cacheKey(string $account, string $scene): string
    {
        $prefix = isValidEmail($account) ? 'email' : 'phone';
        return "{$prefix}_code_{$scene}_" . md5($account);
    }

    /**
     * 发送验证码
     * @throws BusinessException
     */
    public static function send(string $account, string $scene): string
    {
        $theme = EmailEnum::getTheme($scene);

        if (isValidEmail($account)) {
            $code = (string)rand(100000, 999999);
            \Webman\RedisQueue\Redis::send('email_send', [
                'email' => $account,
                'theme' => $theme,
                'code'  => $code,
            ]);
            Cache::set(self::cacheKey($account, $scene), $code, CacheTTLEnum::VERIFY_CODE);
            return '验证码发送成功';
        }

        if (isValidPhone($account)) {
            $code = '123456'; // TODO: 接入真实短信服务
            Cache::set(self::cacheKey($account, $scene), $code, CacheTTLEnum::VERIFY_CODE);
            return '验证码发送成功(测试:123456)';
        }

        throw new BusinessException('请输入正确的邮箱或手机号');
    }

    /**
     * 校验验证码
     * @param bool $consume 校验通过后是否消费（删除）
     */
    public static function verify(string $account, string $code, string $scene, bool $consume = true): bool
    {
        $key = self::cacheKey($account, $scene);
        $cached = Cache::get($key);

        if (!$cached || $cached != $code) {
            return false;
        }

        if ($consume) {
            Cache::delete($key);
        }

        return true;
    }
}

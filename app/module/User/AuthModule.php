<?php

namespace app\module\User;

use app\dep\Permission\RoleDep;
use app\dep\User\UserProfileDep;
use app\dep\User\UsersDep;
use app\dep\User\UserSessionsDep;
use app\enum\CommonEnum;
use app\enum\EmailEnum;
use app\enum\SexEnum;
use app\enum\SystemEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\System\SettingService;
use app\service\User\TokenService;
use app\validate\User\UsersValidate;
use Carbon\Carbon;
use support\Cache;
use support\Redis;

/**
 * 认证模块
 * 负责：登录、登出、刷新令牌、发送验证码、忘记密码
 */
class AuthModule extends BaseModule
{
    protected UsersDep $usersDep;
    protected UserSessionsDep $userSessionsDep;
    protected RoleDep $roleDep;
    protected UserProfileDep $userProfileDep;

    public function __construct()
    {
        $this->usersDep = new UsersDep();
        $this->userSessionsDep = new UserSessionsDep();
        $this->roleDep = new RoleDep();
        $this->userProfileDep = new UserProfileDep();
    }

    /**
     * 获取登录配置（登录类型字典）
     */
    public function getLoginConfig(): array
    {
        $dictService = new DictService();
        $dict = $dictService->setLoginTypeArr()->getDict();
        return self::success($dict);
    }

    /**
     * 登录
     */
    public function login($request): array
    {
        $param = $this->validate($request, UsersValidate::login());

        $loginType = $param['login_type'];

        // 1. 验证阶段
        if ($loginType === SystemEnum::LOGIN_TYPE_PASSWORD) {
            $result = $this->loginByPassword($param, $request);
        } else {
            $result = $this->loginByCode($param, $loginType, $request);
        }

        self::throwIf($result['error'], $result['error'] ?? '登录失败');

        return self::success($this->createSession($result['user']['id'], $param['login_account'], $request, $loginType));
    }

    /**
     * 密码登录
     */
    private function loginByPassword(array $param, $request): array
    {
        if (empty($param['password'])) {
            return ['error' => '请输入密码', 'user' => null];
        }

        $account = $param['login_account'];

        // 智能判断账号类型
        if (isValidEmail($account)) {
            $user = $this->usersDep->findByEmail($account);
        } elseif (is_valid_phone_number($account)) {
            $user = $this->usersDep->findByPhone($account);
        } else {
            return ['error' => '请输入正确的邮箱或手机号', 'user' => null];
        }

        if (!$user) {
            $this->logLoginAttempt(null, $account, SystemEnum::LOGIN_TYPE_PASSWORD, $request, CommonEnum::NO, 'account_not_found');
            return ['error' => '账号或密码错误', 'user' => null];
        }

        if (empty($user['password'])) {
            $this->logLoginAttempt($user['id'], $account, SystemEnum::LOGIN_TYPE_PASSWORD, $request, CommonEnum::NO, 'no_password_set');
            return ['error' => '该账号未设置密码，请使用验证码登录后设置密码', 'user' => null];
        }

        if (!password_verify($param['password'], $user['password'])) {
            $this->logLoginAttempt($user['id'], $account, SystemEnum::LOGIN_TYPE_PASSWORD, $request, CommonEnum::NO, 'wrong_password');
            return ['error' => '账号或密码错误', 'user' => null];
        }

        return ['error' => false, 'user' => $user];
    }

    /**
     * 验证码登录（支持自动注册）
     */
    private function loginByCode(array $param, string $loginType, $request): array
    {
        if (empty($param['code'])) {
            return ['error' => '请输入验证码', 'user' => null];
        }

        // 验证码校验
        if ($loginType === SystemEnum::LOGIN_TYPE_EMAIL) {
            if (!isValidEmail($param['login_account'])) {
                return ['error' => '邮箱格式不正确', 'user' => null];
            }
            $cacheKey = 'email_code_' . md5($param['login_account']);
        } else {
            if (!is_valid_phone_number($param['login_account'])) {
                return ['error' => '手机号格式不正确', 'user' => null];
            }
            $cacheKey = 'phone_code_' . md5($param['login_account']);
        }

        $code = Cache::get($cacheKey);
        if (!$code || $code != $param['code']) {
            $this->logLoginAttempt(null, $param['login_account'], $loginType, $request, CommonEnum::NO, 'invalid_code');
            return ['error' => '验证码错误或已失效', 'user' => null];
        }
        Cache::delete($cacheKey);

        // 查找用户
        $user = $loginType === SystemEnum::LOGIN_TYPE_EMAIL
            ? $this->usersDep->findByEmail($param['login_account'])
            : $this->usersDep->findByPhone($param['login_account']);

        // 自动注册
        if (!$user) {
            if (!SettingService::isRegisterEnabled()) {
                return ['error' => '暂未开放注册', 'user' => null];
            }
            $user = $this->autoRegister($param['login_account'], $loginType);
            if (!$user) {
                return ['error' => '自动注册失败，请稍后重试', 'user' => null];
            }
        }

        return ['error' => false, 'user' => $user];
    }

    /**
     * 自动注册新用户
     */
    private function autoRegister(string $account, string $loginType)
    {
        // 检查是否允许注册
        if (!SettingService::isRegisterEnabled()) {
            return null;
        }

        try {
            return $this->withTransaction(function () use ($account, $loginType) {
                $defaultRole = $this->roleDep->getDefault();
                $roleId = $defaultRole ? $defaultRole['id'] : 0;

                $userData = [
                    'username' => 'User_' . rand(100000, 999999),
                    'password' => null,
                    'role_id' => $roleId,
                    'email' => $loginType === SystemEnum::LOGIN_TYPE_EMAIL ? $account : null,
                    'phone' => $loginType === SystemEnum::LOGIN_TYPE_PHONE ? $account : null,
                ];
                $userId = $this->usersDep->add($userData);

                $this->userProfileDep->add([
                    'user_id' => $userId,
                    'avatar' => SettingService::getDefaultAvatar(),
                    'sex' => SexEnum::UNKNOWN,
                ]);

                return $this->usersDep->find($userId);
            });
        } catch (\Exception $e) {
            // 幂等处理：唯一键冲突时重试查找
            if ($this->isDuplicateKey($e)) {
                return $loginType === SystemEnum::LOGIN_TYPE_EMAIL
                    ? $this->usersDep->findByEmail($account)
                    : $this->usersDep->findByPhone($account);
            }
            return null;
        }
    }

    /**
     * 刷新令牌
     */
    public function refresh($request): array
    {
        $refreshToken = $request->post('refresh_token');
        self::throwIf(!$refreshToken, '缺少刷新令牌', self::CODE_UNAUTHORIZED);

        try {
            $hash = TokenService::hashToken($refreshToken);
        } catch (\Exception $e) {
            self::throw('令牌格式错误', self::CODE_UNAUTHORIZED);
            return []; // unreachable, but satisfies static analysis
        }

        $session = $this->userSessionsDep->findValidByRefreshHash($hash);
        self::throwIf(!$session, '刷新令牌无效或已过期', self::CODE_UNAUTHORIZED);

        self::throwIf(Carbon::parse($session['refresh_expires_at'])->isPast(), '刷新令牌已过期，请重新登录', self::CODE_UNAUTHORIZED);

        $platform = $session['platform'];
        self::throwIf(!$this->checkSingleSessionPolicy($session['user_id'], $platform, $session['id']), '账号已在其他设备登录，请重新登录', self::CODE_UNAUTHORIZED);

        $tokens = TokenService::generateTokenPair();

        $this->userSessionsDep->rotate($session['id'], [
            'access_token_hash' => $tokens['access_token_hash'],
            'refresh_token_hash' => $tokens['refresh_token_hash'],
            'expires_at' => $tokens['access_expires']->toDateTimeString(),
            'refresh_expires_at' => $session['refresh_expires_at'],
            'last_seen_at' => $tokens['now']->toDateTimeString(),
            'ip' => $request->getRealIp(),
            'ua' => $request->header('user-agent'),
        ]);

        if (!empty($session['access_token_hash'])) {
            Redis::connection('token')->del($session['access_token_hash']);
        }

        $this->updateSessionPointer($session['user_id'], $platform, $session['id']);

        return self::success([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['access_ttl']
        ]);
    }

    /**
     * 登出
     */
    public function logout($request): array
    {
        $bearer = $request->header('authorization');
        if (!$bearer) {
            return self::success([], '退出成功');
        }

        try {
            $token = str_replace('Bearer ', '', $bearer);
            $hash = TokenService::hashToken($token);
            $session = $this->userSessionsDep->findValidByAccessHash($hash);

            if ($session) {
                $this->userSessionsDep->revoke($session['id']);
                Redis::connection('token')->del($hash);
                $this->clearSessionPointerIfMatches($session['user_id'], $session['platform'], $session['id']);
            }
        } catch (\Exception $e) {
            // ignore
        }

        return self::success([], '退出成功');
    }

    /**
     * 发送验证码
     */
    public function sendCode($request): array
    {
        $param = $this->validate($request, UsersValidate::sendCode());

        $account = $param['account'];
        $scene = $param['scene'];
        $theme = EmailEnum::getTheme($scene);

        if (isValidEmail($account)) {
            $code = rand(100000, 999999);
            \Webman\RedisQueue\Redis::send('email-send', [
                'email' => $account,
                'theme' => $theme,
                'code' => $code,
            ]);
            Cache::set('email_code_' . md5($account), $code, 300);
            return self::success([], '验证码发送成功');
        }

        if (is_valid_phone_number($account)) {
            $code = 123456; // TODO: 接入真实短信服务
            Cache::set('phone_code_' . md5($account), $code, 300);
            return self::success([], '验证码发送成功(测试:123456)');
        }

        self::throw('请输入正确的邮箱或手机号');
    }

    /**
     * 忘记密码（未登录状态重置密码）
     */
    public function forgetPassword($request): array
    {
        $param = $this->validate($request, UsersValidate::forgetPassword());

        $cacheKey = 'email_code_' . md5($param['email']);
        $code = Cache::get($cacheKey);
        self::throwIf($code != $param['code'], '验证码错误');

        $user = $this->usersDep->findByEmail($param['email']);
        self::throwNotFound($user, '用户不存在');

        $this->usersDep->update($user->id, [
            'password' => password_hash($param['newpassword'], PASSWORD_DEFAULT),
        ]);
        Cache::delete($cacheKey);

        return self::success([], '密码重置成功');
    }

    // ==================== 私有方法 ====================

    /**
     * 创建会话
     */
    private function createSession(int $userId, string $loginAccount, $request, string $loginType = 'email'): array
    {
        $platformHeader = $request->header('platform', 'admin');
        $deviceId = $request->header('device-id', '');

        $tokens = TokenService::generateTokenPair();

        // 单端登录策略（互踢）
        $policyConfig = SettingService::getAuthPolicy($platformHeader ?: 'default');
        if (!empty($policyConfig['single_session_per_platform'])) {
            $oldSessions = $this->userSessionsDep->listActiveByUserPlatform($userId, $platformHeader);
            foreach ($oldSessions as $oldSession) {
                Redis::connection('token')->del($oldSession->access_token_hash);
            }
            $this->userSessionsDep->revokeByUserPlatform($userId, $platformHeader);
        }

        $sessionData = [
            'user_id' => $userId,
            'access_token_hash' => $tokens['access_token_hash'],
            'refresh_token_hash' => $tokens['refresh_token_hash'],
            'platform' => $platformHeader,
            'device_id' => $deviceId,
            'ip' => $request->getRealIp(),
            'ua' => $request->header('user-agent'),
            'expires_at' => $tokens['access_expires']->toDateTimeString(),
            'refresh_expires_at' => $tokens['refresh_expires']->toDateTimeString(),
            'last_seen_at' => $tokens['now']->toDateTimeString(),
            'is_del' => CommonEnum::NO,
        ];

        $sessionId = $this->userSessionsDep->add($sessionData);
        $this->updateSessionPointer($userId, $platformHeader, $sessionId);
        $this->logLoginAttempt($userId, $loginAccount, $loginType, $request, CommonEnum::YES);

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['access_ttl']
        ];
    }

    private function logLoginAttempt(?int $userId, string $loginAccount, string $loginType, $request, int $isSuccess, string $reason = ''): void
    {
        $platformHeader = $request->header('platform', 'admin');
        \Webman\RedisQueue\Redis::send('user-login-log', [
            'user_id' => $userId,
            'login_account' => $loginAccount,
            'login_type' => $loginType,
            'platform' => $platformHeader,
            'ip' => $request->getRealIp(),
            'ua' => $request->header('user-agent'),
            'is_success' => $isSuccess,
            'reason' => $reason,
        ]);
    }

    private function updateSessionPointer(int $userId, string $platform, int $sessionId): void
    {
        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        Redis::connection('token')->set($key, $sessionId, 30 * 24 * 3600);
    }

    private function checkSingleSessionPolicy(int $userId, string $platform, int $currentSessionId): bool
    {
        $policyConfig = SettingService::getAuthPolicy($platform ?: 'default');
        if (empty($policyConfig['single_session_per_platform'])) {
            return true;
        }

        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        $allowedId = Redis::connection('token')->get($key);

        if (!$allowedId) {
            $latest = $this->userSessionsDep->findLatestActiveByUserPlatform($userId, $platform);
            if ($latest) {
                $allowedId = $latest->id;
                Redis::connection('token')->set($key, $allowedId, 30 * 24 * 3600);
            }
        }

        return (!$allowedId || (int)$allowedId === (int)$currentSessionId);
    }

    private function clearSessionPointerIfMatches(int $userId, string $platform, int $sessionId): void
    {
        if (!$platform) return;
        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        $currentPtr = Redis::connection('token')->get($key);
        if ($currentPtr && (int)$currentPtr === (int)$sessionId) {
            Redis::connection('token')->del($key);
        }
    }

    private function isDuplicateKey(\Throwable $e): bool
    {
        if (property_exists($e, 'errorInfo') && is_array($e->errorInfo)) {
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                return true;
            }
        }
        if ($e instanceof \PDOException && isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
            return true;
        }
        $msg = $e->getMessage();
        return (strpos($msg, 'Duplicate entry') !== false) || (strpos($msg, '1062') !== false);
    }

    /**
     * 获取限流用的邮箱/账号 Key
     */
    public static function getRateLimitEmail(): string
    {
        return request()->post('login_account', request()->post('account', ''));
    }
}

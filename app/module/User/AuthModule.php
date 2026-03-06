<?php

namespace app\module\User;

use app\dep\Permission\RoleDep;
use app\dep\User\UserProfileDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\enum\SystemEnum;
use app\module\BaseModule;
use app\service\Permission\AuthPlatformService;
use app\service\System\SettingService;
use app\service\User\SessionService;
use app\service\User\VerifyCodeService;
use app\validate\User\UsersValidate;

/**
 * 认证模块
 * 负责：登录、登出、刷新令牌、发送验证码、忘记密码
 */
class AuthModule extends BaseModule
{
    // ==================== 公开接口 ====================

    /**
     * 获取登录配置（按平台返回允许的登录方式）
     */
    public function getLoginConfig(): array
    {
        $platform = request()->header('platform', '');
        self::throwUnless($platform, '缺少平台标识');

        $allowedTypes = AuthPlatformService::getLoginTypes($platform);
        $filtered = [];
        foreach (SystemEnum::$loginTypeArr as $key => $label) {
            if (\in_array($key, $allowedTypes, true)) {
                $filtered[] = ['label' => $label, 'value' => $key];
            }
        }

        return self::success(['login_type_arr' => $filtered]);
    }

    /**
     * 登录
     */
    public function login($request): array
    {
        $param = $this->validate($request, UsersValidate::login());

        $loginType = $param['login_type'];
        $account = trim($param['login_account']);
        $isNewUser = false;

        if ($loginType === SystemEnum::LOGIN_TYPE_PASSWORD) {
            $user = $this->loginByPassword($account, $param['password'] ?? '');
        } else {
            [$user, $isNewUser] = $this->loginByCode($account, $param['code'] ?? '', $loginType, $request);
        }

        // 用户状态检查
        $this->assertUserActive($user);

        // 平台校验
        $platform = $request->header('platform');
        self::throwIf(
            !$platform || !AuthPlatformService::isValidPlatform($platform),
            '无效的平台标识'
        );

        // 创建会话
        $session = SessionService::create(
            $user['id'],
            $platform,
            $request->header('device-id', ''),
            $request->getRealIp(),
            $request->header('user-agent')
        );

        // 记录登录日志
        $this->logLoginAttempt($user['id'], $account, $loginType, $request, CommonEnum::YES);

        $session['is_new_user'] = $isNewUser;
        return self::success($session);
    }

    /**
     * 刷新令牌
     */
    public function refresh($request): array
    {
        $refreshToken = $request->post('refresh_token');
        self::throwUnless($refreshToken, '缺少刷新令牌', self::CODE_UNAUTHORIZED);

        $result = SessionService::refresh(
            $refreshToken,
            $request->getRealIp(),
            $request->header('user-agent')
        );

        return self::success($result);
    }

    /**
     * 登出
     */
    public function logout($request): array
    {
        $bearer = $request->header('authorization');
        if ($bearer) {
            SessionService::revoke($bearer);
        }

        return self::success([], '退出成功');
    }

    /**
     * 发送验证码
     */
    public function sendCode($request): array
    {
        $param = $this->validate($request, UsersValidate::sendCode());
        $msg = VerifyCodeService::send($param['account'], $param['scene']);
        return self::success([], $msg);
    }

    /**
     * 忘记密码
     */
    public function forgetPassword($request): array
    {
        $param = $this->validate($request, UsersValidate::forgetPassword());

        $account = $param['account'];

        self::throwIf(
            $param['new_password'] !== $param['confirm_password'],
            '两次输入的密码不一致'
        );

        // 查找用户
        $user = $this->resolveUser($account);
        self::throwNotFound($user, '该账号未注册');
        $this->assertUserActive($user);

        // 验证码校验
        self::throwIf(
            !VerifyCodeService::verify($account, $param['code'], 'forget'),
            '验证码错误或已失效'
        );

        // 更新密码
        $this->dep(UsersDep::class)->update($user->id, [
            'password' => password_hash($param['new_password'], PASSWORD_DEFAULT),
        ]);

        return self::success([], '密码重置成功');
    }

    /**
     * 获取限流用的邮箱/账号 Key
     */
    public static function getRateLimitEmail(): string
    {
        return request()->post('login_account', request()->post('account', ''));
    }

    // ==================== 私有方法 ====================

    /**
     * 密码登录
     * @throws \app\exception\BusinessException
     */
    private function loginByPassword(string $account, string $password)
    {
        self::throwUnless($password, '请输入密码');

        $user = $this->resolveUser($account);
        self::throwUnless($user, '账号或密码错误');

        if (empty($user['password'])) {
            self::throw('该账号未设置密码，请使用验证码登录后设置密码');
        }

        if (!password_verify($password, $user['password'])) {
            $this->logLoginAttempt($user['id'], $account, SystemEnum::LOGIN_TYPE_PASSWORD, request(), CommonEnum::NO, 'wrong_password');
            self::throw('账号或密码错误');
        }

        return $user;
    }

    /**
     * 验证码登录（支持自动注册）
     * @return array [user, isNewUser]
     * @throws \app\exception\BusinessException
     */
    private function loginByCode(string $account, string $code, string $loginType, $request): array
    {
        self::throwUnless($code, '请输入验证码');

        $accountType = $this->getAccountType($account);
        self::throwUnless($accountType, $loginType === SystemEnum::LOGIN_TYPE_EMAIL ? '邮箱格式不正确' : '手机号格式不正确');

        // 验证码校验（绑定 login 场景）
        if (!VerifyCodeService::verify($account, $code, 'login', false)) {
            $this->logLoginAttempt(null, $account, $loginType, $request, CommonEnum::NO, 'invalid_code');
            self::throw('验证码错误或已失效');
        }

        // 查找用户
        $user = $this->resolveUser($account);
        $isNewUser = false;

        if (!$user) {
            // 先检查注册策略，再消费验证码
            $platform = $request->header('platform');
            self::throwUnless($platform, '缺少平台标识');
            self::throwIf(!AuthPlatformService::isRegisterEnabled($platform), '暂未开放注册');

            // 注册策略通过，消费验证码
            VerifyCodeService::verify($account, $code, 'login', true);
            $user = $this->autoRegister($account, $loginType);
            self::throwUnless($user, '自动注册失败，请稍后重试');
            $isNewUser = true;
        } else {
            // 用户存在，消费验证码
            VerifyCodeService::verify($account, $code, 'login', true);
        }

        return [$user, $isNewUser];
    }

    /**
     * 智能解析账号 → 用户
     */
    private function resolveUser(string $account)
    {
        $dep = $this->dep(UsersDep::class);

        if (isValidEmail($account)) {
            return $dep->findByEmail($account);
        }
        if (isValidPhone($account)) {
            return $dep->findByPhone($account);
        }

        self::throw('请输入正确的邮箱或手机号');
        return null;
    }

    /**
     * 获取账号类型
     */
    private function getAccountType(string $account): ?string
    {
        if (isValidEmail($account)) return 'email';
        if (isValidPhone($account)) return 'phone';
        return null;
    }

    /**
     * 检查用户是否可用（未删除 + 未禁用）
     * @throws \app\exception\BusinessException
     */
    private function assertUserActive($user): void
    {
        self::throwIf(
            isset($user['is_del']) && $user['is_del'] == CommonEnum::YES,
            '账号不存在'
        );
        self::throwIf(
            isset($user['status']) && $user['status'] != CommonEnum::YES,
            '账号已被禁用，请联系管理员'
        );
    }

    /**
     * 自动注册新用户
     */
    private function autoRegister(string $account, string $loginType)
    {
        try {
            return $this->withTransaction(function () use ($account, $loginType) {
                $defaultRole = $this->dep(RoleDep::class)->getDefault();
                self::throwUnless($defaultRole, '系统未配置默认角色，无法注册');

                $userId = $this->dep(UsersDep::class)->add([
                    'username' => 'User_' . substr(uniqid(), -8),
                    'password' => null,
                    'role_id'  => $defaultRole['id'],
                    'email'    => $loginType === SystemEnum::LOGIN_TYPE_EMAIL ? $account : null,
                    'phone'    => $loginType === SystemEnum::LOGIN_TYPE_PHONE ? $account : null,
                ]);

                $this->dep(UserProfileDep::class)->add([
                    'user_id' => $userId,
                    'avatar'  => SettingService::getDefaultAvatar(),
                    'sex'     => CommonEnum::SEX_UNKNOWN,
                ]);

                return $this->dep(UsersDep::class)->find($userId);
            });
        } catch (\Exception $e) {
            if (self::isDuplicateKey($e)) {
                return $loginType === SystemEnum::LOGIN_TYPE_EMAIL
                    ? $this->dep(UsersDep::class)->findByEmail($account)
                    : $this->dep(UsersDep::class)->findByPhone($account);
            }
            return null;
        }
    }

    /**
     * 记录登录日志（异步队列）
     */
    private function logLoginAttempt(?int $userId, string $account, string $loginType, $request, int $isSuccess, string $reason = ''): void
    {
        \Webman\RedisQueue\Redis::send('user_login_log', [
            'user_id'       => $userId,
            'login_account' => $account,
            'login_type'    => $loginType,
            'platform'      => $request->header('platform') ?? '',
            'ip'            => $request->getRealIp(),
            'ua'            => $request->header('user-agent'),
            'is_success'    => $isSuccess,
            'reason'        => $reason,
        ]);
    }

}

<?php

namespace app\module\User;

use app\dep\AddressDep;
use app\dep\User\PermissionDep;
use app\dep\User\RoleDep;
use app\dep\User\UserProfileDep;
use app\dep\User\UsersDep;
use app\dep\User\UserSessionsDep;
use app\enum\CommonEnum;
use app\enum\EmailEnum;
use app\enum\SexEnum;
use app\enum\SystemEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\User\PermissionService;
use app\service\User\TokenService;
use app\validate\User\UsersValidate;
use Carbon\Carbon;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;
use support\Cache;
use support\Redis;

class UsersModule extends BaseModule
{

    public $UserDep;
    public $UserSessionsDep;
    public $RoleDep;
    public $PermissionDep;
    public $AddressDep;
    public $UserProfileDep;
    public $permissionService;

    public function __construct()
    {
        $this->UserDep = new UsersDep();
        $this->UserSessionsDep = new UserSessionsDep();
        $this->RoleDep = new RoleDep();
        $this->PermissionDep = new PermissionDep();
        $this->AddressDep = new AddressDep();
        $this->UserProfileDep = new UserProfileDep();
        $this->permissionService = new PermissionService();

    }

    public function register($request)
    {
        return self::error('注册功能已合并至登录，请直接登录');
    }


    public function getLoginConfig()
    {
        $dictService = new DictService();
        $dict = $dictService->setLoginTypeArr()->getDict();
        return self::success($dict);
    }

    /**
     * 判断异常是否为唯一键冲突 (MySQL Error 1062)
     */
    private function isDuplicateKey(\Throwable $e): bool
    {
        // 1) Illuminate/DBAL 类异常经常有 errorInfo
        if (property_exists($e, 'errorInfo') && is_array($e->errorInfo)) {
            // MySQL duplicate entry error code
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                return true;
            }
        }

        // 2) PDOException 也可能有 errorInfo
        if ($e instanceof \PDOException && isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
            return true;
        }

        // 3) 兜底：从 message 判断（不同驱动 message 会有差异）
        $msg = $e->getMessage();
        return (strpos($msg, 'Duplicate entry') !== false) || (strpos($msg, '1062') !== false);
    }


    public function login($request)
    {
        try {
            $param = $this->validate($request, UsersValidate::login());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $loginType = $param['login_type'];

        // 1. 验证阶段
        if ($loginType === SystemEnum::LOGIN_TYPE_PASSWORD) {
            if (empty($param['password'])) {
                return self::error('请输入密码');
            }
            $account = $param['login_account'];

            // 智能判断账号类型 (Email / Phone)
            if (isValidEmail($account)) {
                $resDep = $this->UserDep->firstByEmail($account);
            } elseif (is_valid_phone_number($account)) {
                $resDep = $this->UserDep->firstByPhone($account);
            } else {
                // 如果既不是邮箱也不是手机号，直接返回错误（因为username已废弃作为登录凭证）
                return self::error('请输入正确的邮箱或手机号');
            }

            if (!$resDep) return self::error('账号或密码错误');
            if (empty($resDep['password'])) return self::error('该账号未设置密码，请使用验证码登录后设置密码');
            if (!password_verify($param['password'], $resDep['password'])) return self::error('账号或密码错误');

        } else {
            // 邮箱或手机号登录 (需验证码)
            if (empty($param['code'])) {
                return self::error('请输入验证码');
            }

            $cacheKey = '';
            if ($loginType === SystemEnum::LOGIN_TYPE_EMAIL) {
                if (!isValidEmail($param['login_account'])) return self::error('邮箱格式不正确');
                $cacheKey = 'email_code_' . md5($param['login_account']);
            } elseif ($loginType === SystemEnum::LOGIN_TYPE_PHONE) {
                if (!is_valid_phone_number($param['login_account'])) return self::error('手机号格式不正确');
                $cacheKey = 'phone_code_' . md5($param['login_account']);
            }

            $code = Cache::get($cacheKey);
            if (!$code || $code != $param['code']) {
                return self::error('验证码错误或已失效');
            }
            Cache::delete($cacheKey);

            // 查找用户
            if ($loginType === SystemEnum::LOGIN_TYPE_EMAIL) {
                $resDep = $this->UserDep->firstByEmail($param['login_account']);
            } else {
                $resDep = $this->UserDep->firstByPhone($param['login_account']);
            }

            // 自动注册逻辑
            if (!$resDep) {
                \support\Db::beginTransaction();
                try {
                    // Get Default Role
                    $defaultRole = $this->RoleDep->firstByDefault();
                    $roleId = $defaultRole ? $defaultRole['id'] : 0;

                    $userData = [
                        'username' => 'User_' . rand(100000, 999999), // 生成默认随机昵称
                        'password' => null, // 无密码
                        'role_id' => $roleId,
                        'email' => $loginType === SystemEnum::LOGIN_TYPE_EMAIL ? $param['login_account'] : null,
                        'phone' => $loginType === SystemEnum::LOGIN_TYPE_PHONE ? $param['login_account'] : null,
                    ];
                    $userId = $this->UserDep->add($userData);

                    $this->UserProfileDep->add([
                        'user_id' => $userId,
                        'avatar' => config('app.default_avatar', ''),
                        'sex' => SexEnum::UNKNOWN,
                    ]);

                    \support\Db::commit();

                    // 重新获取用户对象
                    $resDep = $this->UserDep->first($userId);
                } catch (\Exception $e) {
                    \support\Db::rollBack();

                    // 幂等处理：如果是唯一键冲突，说明并发请求已经创建了用户
                    if ($this->isDuplicateKey($e)) {
                        // 重试查找用户
                        if ($loginType === SystemEnum::LOGIN_TYPE_EMAIL) {
                            $resDep = $this->UserDep->firstByEmail($param['login_account']);
                        } else {
                            $resDep = $this->UserDep->firstByPhone($param['login_account']);
                        }

                        // 如果重查还是没找到（极小概率），则抛出原异常
                        if (!$resDep) {
                            return self::error('自动注册失败(并发冲突): ' . $e->getMessage());
                        }
                        // 如果找到了，继续下面的登录逻辑
                    } else {
                        // logger()->error('auto register failed', ['err' => (string)$e]);
                        return self::error('自动注册失败，请稍后重试');
                    }
                }
            }
        }

        return self::response($this->createSession($resDep['id'], $param['login_account'], $request, $loginType));
    }

    /**
     * 通用会话创建逻辑
     * 包含：生成Token、策略检查(互踢)、写入Session DB、更新Redis指针、异步日志
     */
    private function createSession(int $userId, string $loginAccount, $request, string $loginType = 'email'): array
    {
        $platformHeader = $request->header('platform', 'admin');
        $deviceId = $request->header('device-id', '');

         // 1. 生成Token对
        $tokens = TokenService::generateTokenPair();

        // 2. 单端登录策略检查 (互踢)
        $policyConfig = config('auth.policies.' . ($platformHeader ?: 'default')) ?? config('auth.default_policy');
        if (!empty($policyConfig['single_session_per_platform'])) {
            $oldSessions = $this->UserSessionsDep->listActiveByUserPlatform($userId, $platformHeader);
            foreach ($oldSessions as $oldSession) {
                Redis::connection('token')->del($oldSession->access_token_hash);
            }
            $this->UserSessionsDep->revokeByUserPlatform($userId, $platformHeader);
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
            'created_at' => $tokens['now']->toDateTimeString(),
            'updated_at' => $tokens['now']->toDateTimeString(),
            'is_del' => CommonEnum::NO,
        ];

        // 3. 写入会话到数据库
        $sessionId = $this->UserSessionsDep->add($sessionData);

        // 4. 更新Redis指针
        $this->updateSessionPointer($userId, $platformHeader, $sessionId);

        // 5. 异步日志 (成功)
        \Webman\RedisQueue\Redis::send('user-login-log', [
            'user_id' => $userId,
            'login_account' => $loginAccount, // 记录当前登录的账号
            'login_type' => $loginType, // 记录准确的登录类型
            'platform' => $platformHeader,
            'ip' => $request->getRealIp(),
            'ua' => $request->header('user-agent'),
            'success' => 1,
            'created_at' => $tokens['now']->toDateTimeString(),
        ]);

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['access_ttl']
        ];
    }

    public function refresh($request)
    {
        $refreshToken = $request->post('refresh_token');
        if (!$refreshToken) return self::error('缺少刷新令牌', 401);

        try {
            $hash = TokenService::hashToken($refreshToken);
        } catch (\Exception $e) {
            return self::error('令牌格式错误', 401);
        }

        $session = $this->UserSessionsDep->firstValidByRefreshHash($hash);
        if (!$session) return self::error('刷新令牌无效或已过期', 401);

        if (Carbon::parse($session['refresh_expires_at'])->isPast()) {
            return self::error('刷新令牌已过期，请重新登录', 401);
        }

        // Check Policy
        $platform = $session['platform'];
        if (!$this->checkSingleSessionPolicy($session['user_id'], $platform, $session['id'])) {
            return self::error('账号已在其他设备登录，请重新登录', 401);
        }

        $tokens = TokenService::generateTokenPair();

        // Rotate Session
        $this->UserSessionsDep->rotateById($session['id'], [
            'access_token_hash' => $tokens['access_token_hash'],
            'refresh_token_hash' => $tokens['refresh_token_hash'],
            'expires_at' => $tokens['access_expires']->toDateTimeString(),
            'refresh_expires_at' => $session['refresh_expires_at'],
            'last_seen_at' => $tokens['now']->toDateTimeString(),
            'ip' => $request->getRealIp(),
            'ua' => $request->header('user-agent'),
        ]);

        // Cleanup Old Access Token
        if (!empty($session['access_token_hash'])) {
            Redis::connection('token')->del($session['access_token_hash']);
        }

        // Update Pointer
        $this->updateSessionPointer($session['user_id'], $platform, $session['id']);

        return self::response([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['access_ttl']
        ]);
    }

    public function logout($request)
    {
        $bearer = $request->header('authorization');
        if (!$bearer) return self::response([], '退出成功');

        try {
            $token = str_replace('Bearer ', '', $bearer);
            $hash = TokenService::hashToken($token);
            $session = $this->UserSessionsDep->firstValidByAccessHash($hash);

            if ($session) {
                $this->UserSessionsDep->revokeById($session['id']);
                Redis::connection('token')->del($hash);
                $this->clearSessionPointerIfMatches($session['user_id'], $session['platform'], $session['id']);
            }
        } catch (\Exception $e) {
        }

        return self::response([], '退出成功');
    }

    /**
     * Redis Session Pointer Helpers
     */
    private function updateSessionPointer($userId, $platform, $sessionId)
    {
        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        Redis::connection('token')->set($key, $sessionId, 30 * 24 * 3600);
    }

    private function checkSingleSessionPolicy($userId, $platform, $currentSessionId)
    {
        $policyConfig = config('auth.policies.' . ($platform ?: 'default')) ?? config('auth.default_policy');
        if (empty($policyConfig['single_session_per_platform'])) {
            return true;
        }

        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        $allowedId = Redis::connection('token')->get($key);

        if (!$allowedId) {
            $latest = $this->UserSessionsDep->firstLatestActiveByUserPlatform($userId, $platform);
            if ($latest) {
                $allowedId = $latest->id;
                Redis::connection('token')->set($key, $allowedId, 30 * 24 * 3600);
            }
        }

        return (!$allowedId || (int)$allowedId === (int)$currentSessionId);
    }

    private function clearSessionPointerIfMatches($userId, $platform, $sessionId)
    {
        if (!$platform) return;
        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        $currentPtr = Redis::connection('token')->get($key);
        if ($currentPtr && (int)$currentPtr === (int)$sessionId) {
            Redis::connection('token')->del($key);
        }
    }


    /**
     * 获取限流用的邮箱Key
     */
    public static function getRateLimitEmail(): string
    {
        return request()->post('email', '');
    }

    public function sendCode($request)
    {
        try {
            $param = $this->validate($request, UsersValidate::sendCode());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $account = $param['login_account'];

        // 判断是邮箱还是手机号
        if (isValidEmail($account)) {
            // 邮箱发送逻辑
            $code = rand(100000, 999999);
            $theme = isset(EmailEnum::$statusArr[$param['status'] ?? 0]) ? EmailEnum::$statusArr[$param['status'] ?? 0] : '默认主题';
            $queue = "email-send";
            $data = [
                'email' => $account,
                'theme' => $theme,
                'code' => $code,
            ];
            \Webman\RedisQueue\Redis::send($queue, $data);

            $cacheKey = 'email_code_' . md5($account);
            Cache::set($cacheKey, $code, 300);
            return self::response([], '验证码发送成功');

        } elseif (is_valid_phone_number($account)) {
            // 手机号发送逻辑 (Mock)
            $code = 123456; // 方便测试
            // TODO: 接入真实的短信发送服务
            $cacheKey = 'phone_code_' . md5($account);
            Cache::set($cacheKey, $code, 300);
            return self::response([], '验证码发送成功(测试:123456)');
        } else {
            return self::error('请输入正确的邮箱或手机号');
        }
    }

    public function forgetPassword($request)
    {
        try {
            $param = v::input($request->all(), [
                'email' => v::email()->setName('邮箱'),
                'newpassword' => v::length(6, 64)->setName('新密码'),
                'code' => v::digit()->length(6, 6)->setName('验证码')
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }

        $dep = $this->UserDep;

        // 用 MD5 生成缓存 key，保持与 sendCode 中一致
        $cacheKey = 'email_code_' . md5($param['email']);
        $code = Cache::get($cacheKey);
        if ($code != $param['code']) {
            return self::error('验证码错误');
        }

        $resdep = $dep->firstByEmail($param['email']);
        $data = [
            'password' => password_hash($param['newpassword'], PASSWORD_DEFAULT),
        ];

        $dep->edit($resdep->id, $data);

        return self::response();
    }

    public function init($request)
    {
        $user = $this->UserDep->first($request->userId);
        if (!$user) {
            return self::error('用户不存在');
        }
        $profile = $this->UserProfileDep->firstByUserId($user->id);

        $base = [
            'user_id' => $user->id,
            'username' => $user->username,
            'avatar' => $profile->avatar,
        ];

        // 把 user 直接传下去
        $perm = $this->permissionService->buildPermissionContextByUser($user);

        Cache::set(
            'auth_perm_uid_' . $user->id,
            $perm['buttonCodes'],
            300
        );

        return self::success(array_merge($base, $perm));
    }

    public function initPersonal($request)
    {
        $param = $request->all();
        $dictService = new DictService();
        $user = $this->UserDep->first($param['user_id']);
        $profile = $this->UserProfileDep->firstByUserId($user->id);
        $resRole = $this->RoleDep->first($user->role_id);
        $data['list'] = [
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $profile->avatar,
            'phone' => $user->phone,
            'role_id' => $user->role_id,
            'role_name' => $resRole['name'],
            'address' => (int)($profile->address_id ?? 0),
            'detail_address' => $profile->detail_address ?? '',
            'sex' => (int)($profile->sex ?? 1),
            'birthday' => $profile->birthday ?? '',
            'bio' => $profile->bio ?? '',
            'is_self' => $param['user_id'] == $request->userId ? CommonEnum::YES : CommonEnum::NO,
            'has_password' => !empty($user->password), // 是否已设置密码
        ];

        $dict = $dictService
            ->setAuthAdressTree()
            ->setSexArr()
            ->setVerifyTypeArr()
            ->getDict();

        $data['dict'] = $dict;
        return self::response($data);
    }

    public function editPersonal($request)
    {
        try {
            $param = v::input($request->all(), [
                'username' => v::length(1, 50)->setName('用户名'),
                'avatar' => v::optional(v::stringType()),
                'phone'          => v::optional(v::stringType()),
                'sex'            => v::intVal()->setName('性别'),
                'birthday'       => v::optional(v::stringType())->setName('生日'),
                'address'        => v::intVal()->setName('地址'),
                'detail_address' => v::optional(v::stringType()),
                'bio'            => v::optional(v::stringType())
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $userDep = $this->UserDep;
        $profileDep = $this->UserProfileDep;
        $user = $this->UserDep->first($request->userId);;
        if (isset($param['phone']) && trim((string)$param['phone']) !== '' && !is_valid_phone_number($param['phone'])) {
            return self::error('无效的手机号码');
        }
        // 验证手机号

        $userData = [
            'username' => $param['username'],
            'phone' => $param['phone'] ?? '',
        ];

        $profileData = [
            'avatar' => $param['avatar'] ?? null,
            'sex' => (int)$param['sex'],
            'birthday' => $param['birthday'] ?? null,
            'address_id' => (int)$param['address'],
            'detail_address' => $param['detail_address'] ?? '',
            'bio' => $param['bio'] ?? '',
        ];

        $userDep->edit($user->id, $userData);
        $profileDep->editByUserId($user->id, $profileData);

        return self::response();
    }

    public function EditPassword($request)
    {
        try {
            $param = v::input($request->all(), [
                'password' => v::length(6, 64)->setName('原始密码'),
                'newpassword' => v::length(6, 64)->setName('新密码'),
                'respassword' => v::length(6, 64)->setName('确认新密码')
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $dep = $this->UserDep;
        $user = $this->UserDep->first($request->userId);;

        // 检查必填项是否为空

        // 验证原密码是否正确
        if (!password_verify($param['password'], $user->password)) {
            return self::error('原密码不正确');
        }

        // 确保新密码与确认密码一致
        if ($param['newpassword'] !== $param['respassword']) {
            return self::error('新密码不一致');
        }

        // 防止新密码与原密码相同
        if (password_verify($param['newpassword'], $user->password)) {
            return self::error('新密码不能与原密码一致');
        }

        // 加密新密码
        $data = [
            'password' => password_hash($param['newpassword'], PASSWORD_DEFAULT),
        ];

        // 更新密码
        $dep->edit($user->id, $data);

        return self::response([], '密码修改成功', 200);
    }

    /**
     * 更新手机号（需登录 + 新手机号验证码）
     */
    public function updatePhone($request)
    {
        try {
            $param = $this->validate($request, UsersValidate::updatePhone());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $phone = $param['phone'];

        // 验证手机号格式
        if (!is_valid_phone_number($phone)) {
            return self::error('手机号格式不正确');
        }

        // 验证码校验
        $cacheKey = 'phone_code_' . md5($phone);
        $code = Cache::get($cacheKey);
        if (!$code || $code != $param['code']) {
            return self::error('验证码错误或已失效');
        }

        // 检查手机号是否已被其他用户占用
        $exists = $this->UserDep->firstByPhone($phone);
        if ($exists && $exists['id'] != $request->userId) {
            return self::error('该手机号已被其他账号绑定');
        }

        // 更新手机号
        $this->UserDep->edit($request->userId, ['phone' => $phone]);
        Cache::delete($cacheKey);

        return self::response([], '手机号绑定成功');
    }

    /**
     * 更新邮箱（需登录 + 新邮箱验证码）
     */
    public function updateEmail($request)
    {
        try {
            $param = $this->validate($request, UsersValidate::updateEmail());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $email = $param['email'];

        // 验证码校验
        $cacheKey = 'email_code_' . md5($email);
        $code = Cache::get($cacheKey);
        if (!$code || $code != $param['code']) {
            return self::error('验证码错误或已失效');
        }

        // 检查邮箱是否已被其他用户占用
        $exists = $this->UserDep->firstByEmail($email);
        if ($exists && $exists['id'] != $request->userId) {
            return self::error('该邮箱已被其他账号绑定');
        }

        // 更新邮箱
        $this->UserDep->edit($request->userId, ['email' => $email]);
        Cache::delete($cacheKey);

        return self::response([], '邮箱绑定成功');
    }

    /**
     * 更新密码（需登录）
     * 支持两种验证方式：
     * 1. 原密码验证（verify_type = password）
     * 2. 验证码验证（verify_type = code）—— 适用于忘记原密码或首次设置
     */
    public function updatePassword($request)
    {
        try {
            $param = $this->validate($request, UsersValidate::updatePassword());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $user = $this->UserDep->first($request->userId);
        $verifyType = $param['verify_type'];

        // 密码一致性检查
        if ($param['new_password'] !== $param['confirm_password']) {
            return self::error('两次输入的密码不一致');
        }

        // 根据验证类型进行身份验证
        if ($verifyType === SystemEnum::VERIFY_TYPE_PASSWORD) {
            // 原密码验证
            if (empty($param['old_password'])) {
                return self::error('请输入原密码');
            }
            if (empty($user->password)) {
                return self::error('您尚未设置密码，请使用验证码方式');
            }
            if (!password_verify($param['old_password'], $user->password)) {
                return self::error('原密码错误');
            }
        } else {
            // 验证码验证
            if (empty($param['code'])) {
                return self::error('请输入验证码');
            }

            // 优先使用邮箱，其次手机号
            $account = $user->email ?: $user->phone;
            if (!$account) {
                return self::error('请先绑定邮箱或手机号');
            }

            // 根据账号类型确定缓存Key
            if (isValidEmail($account)) {
                $cacheKey = 'email_code_' . md5($account);
            } else {
                $cacheKey = 'phone_code_' . md5($account);
            }

            $code = Cache::get($cacheKey);
            if (!$code || $code != $param['code']) {
                return self::error('验证码错误或已失效');
            }
            Cache::delete($cacheKey);
        }

        // 防止新密码与原密码相同
        if (!empty($user->password) && password_verify($param['new_password'], $user->password)) {
            return self::error('新密码不能与原密码相同');
        }

        // 更新密码
        $this->UserDep->edit($user->id, [
            'password' => password_hash($param['new_password'], PASSWORD_DEFAULT)
        ]);

        return self::response([], '密码设置成功');
    }


}

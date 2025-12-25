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
        try {
            $param = $this->validate($request, UsersValidate::register());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $dep = $this->UserDep;

        if ($param['password'] != $param['respassword']) {
            return self::error('两次密码不一致');
        }

        // 用 MD5 生成安全的缓存 key
        $cacheKey = 'email_code_' . md5($param['email']);
        $code = Cache::get($cacheKey);
        if ($code != $param['code']) {
            return self::error('验证码错误');
        }

        if ($dep->firstByEmail($param['email'])) {
            return self::error('邮箱已存在');
        }

        // Get Default Role
        $defaultRole = $this->RoleDep->firstByDefault();
        $roleId = $defaultRole ? $defaultRole['id'] : 0;

        // Create User
        $userData = [
            'email' => $param['email'],
            'password' => password_hash($param['password'], PASSWORD_DEFAULT),
            'username' => $param['username'],
            'role_id'  => $roleId,
        ];

        $userId = $dep->add($userData);

        // Create User Profile
        $this->UserProfileDep->add([
            'user_id' => $userId,
            'avatar'  => config('app.default_avatar', ''),
            'sex' => SexEnum::UNKNOWN,
        ]);

        // Auto Login Logic: Create Session & Return Tokens
        return self::response($this->createSession($userId, $param['email'], $request));
    }


    public function getLoginConfig()
    {
        $dictService = new DictService();
        $dict = $dictService->setLoginTypeArr()->getDict();
        return self::success($dict);
    }

    public function login($request)
    {
        try {
            $param = $this->validate($request, UsersValidate::login());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $platformHeader = $request->header('platform', 'web');
        $loginType = $param['login_type'];
        $resDep = null;

        // 根据 login_type 精准查找
        if ($loginType === SystemEnum::LOGIN_TYPE_EMAIL) {
            if (!isValidEmail($param['login_account'])) {
                return self::error('邮箱格式不正确');
            }
            $resDep = $this->UserDep->firstByEmail($param['login_account']);
        } elseif ($loginType === SystemEnum::LOGIN_TYPE_PHONE) {
            // 简单校验手机号格式
            if (!is_valid_phone_number($param['login_account'])) {
                return self::error('手机号格式不正确');
            }
            $resDep = $this->UserDep->firstByPhone($param['login_account']);
        }

        $logData = [
            'login_account' => $param['login_account'],
            'login_type' => $loginType,
            'platform' => $platformHeader,
            'ip' => $request->getRealIp(),
            'ua' => $request->header('user-agent'),
            'created_at' => date('Y-m-d H:i:s'),
            'success' => 0,
        ];

        if (!$resDep) {
            $logData['reason'] = '账号不存在';
            \Webman\RedisQueue\Redis::send('user-login-log', $logData);
            return self::error('账号或密码错误');
        }

        $logData['user_id'] = $resDep['id'];

        if (!password_verify($param['password'], $resDep['password'])) {
            $logData['reason'] = '密码错误';
            \Webman\RedisQueue\Redis::send('user-login-log', $logData);
            return self::error('账号或密码错误');
        }

        // Create Session & Return Tokens
        return self::response($this->createSession($resDep['id'], $param['login_account'], $request, $loginType));
    }

    /**
     * 通用会话创建逻辑
     * 包含：生成Token、策略检查(互踢)、写入Session DB、更新Redis指针、异步日志
     */
    private function createSession(int $userId, string $loginAccount, $request, string $loginType = 'email'): array
    {
        $platformHeader = $request->header('platform', 'web');
        $deviceId = $request->header('device-id', '');

        // 1. Generate Tokens
        $tokens = TokenService::generateTokenPair();

        // 2. Check Policies: single_session_per_platform (Revoke Old)
        $policyConfig = config('auth.policies.' . ($platformHeader ?: 'default')) ?? config('auth.default_policy');
        if (!empty($policyConfig['single_session_per_platform'])) {
            $oldSessions = $this->UserSessionsDep->listActiveByUserPlatform($userId, $platformHeader);
            foreach ($oldSessions as $oldSession) {
                Redis::connection('token')->del($oldSession->access_token_hash);
            }
            $this->UserSessionsDep->revokeByUserPlatform($userId, $platformHeader);
        }

        // 3. Create Session Data
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

        // 4. Insert DB
        $sessionId = $this->UserSessionsDep->add($sessionData);

        // 5. Update Redis Pointer
        $this->updateSessionPointer($userId, $platformHeader, $sessionId);

        // 6. Async Log (Success)
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
        $platform = $request->header('platform', 'admin');
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
        } catch (\Exception $e) {}

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

        // 生成验证码
        $code = rand(100000, 999999);
        // 获取邮件主题，防止状态传值错误，建议加个 isset 判断
        $theme = isset(EmailEnum::$statusArr[$param['status']]) ? EmailEnum::$statusArr[$param['status']] : '默认主题';
        $queue = "email-send";
        $data = [
            'email' => $param['email'],
            'theme' => $theme,
            'code' => $code,
        ];
        // 显式使用 RedisQueue 的 Redis 类发送消息，避免与 support\Redis 冲突
        \Webman\RedisQueue\Redis::send($queue, $data);

        // 使用 MD5 对 email 进行哈希，保证缓存 key 中没有特殊字符
        $cacheKey = 'email_code_' . md5($param['email']);
        // 设置验证码缓存，5 分钟有效，300 秒
        Cache::set($cacheKey, $code, 300);
        return self::response([], '验证码发送成功');
    }

    public function forgetPassword($request)
    {
        try {
            $param = v::input($request->all(), [
                'email'      => v::email()->setName('邮箱'),
                'newpassword'=> v::length(6, 64)->setName('新密码'),
                'code'       => v::digit()->length(6, 6)->setName('验证码')
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
            'user_id'  => $user->id,
            'username' => $user->username,
            'avatar'   => $profile->avatar,
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
        $user = $this->UserDep->first($param['user_id']);;
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
            'bio' => $profile->bio ?? '',
            'is_self' => $param['user_id'] == $request->userId ? CommonEnum::YES : CommonEnum::NO,
        ];

        $dict = $dictService
            ->setAuthAdressTree()
            ->setSexArr()
            ->getDict();

        $data['dict'] = $dict;
        return self::response($data);
    }

    public function editPersonal($request)
    {
        try {
            $param = v::input($request->all(), [
                'username'       => v::length(1, 50)->setName('用户名'),
                'avatar'         => v::optional(v::stringType()),
                'phone'          => v::optional(v::stringType()),
                'sex'            => v::intVal()->setName('性别'),
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
                'password'    => v::length(6, 64)->setName('原始密码'),
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
}

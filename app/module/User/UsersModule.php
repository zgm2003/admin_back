<?php

namespace app\module\User;

//导入部分
use app\dep\AddressDep;
use app\dep\User\PermissionDep;
use app\dep\User\RoleDep;
use app\dep\User\UsersDep;
use app\dep\User\UserSessionsDep;
use app\dep\User\UsersLoginLogDep;
use app\dep\User\UsersTokenDep;
use app\dep\User\UserProfileDep;
use app\enum\CommonEnum;
use app\enum\EmailEnum;
use app\enum\PermissionEnum;
use app\enum\SexEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\TokenService;
use Carbon\Carbon;
use support\Cache;
use support\Redis;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;
use app\validate\User\UsersValidate;

class UsersModule extends BaseModule
{

    public $UserDep;
    public $UserTokenDep;
    public $UserSessionsDep;
    public $UsersLoginLogDep;
    public $RoleDep;
    public $PermissionDep;
    public $AddressDep;
    public $UserProfileDep;

    public function __construct()
    {
        $this->UserDep = new UsersDep();
        $this->UserTokenDep = new UsersTokenDep();
        $this->UserSessionsDep = new UserSessionsDep();
        $this->UsersLoginLogDep = new UsersLoginLogDep();
        $this->RoleDep = new RoleDep();
        $this->PermissionDep = new PermissionDep();
        $this->AddressDep = new AddressDep();
        $this->UserProfileDep = new UserProfileDep();

    }

    public function register($request)
    {
        try {
            $param = $this->validate($request, UsersValidate::register());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $dep = $this->UserDep;
        $sessionDep = $this->UserSessionsDep;
        $logDep = $this->UsersLoginLogDep;

        if ($param['password'] != $param['respassword']) {
            return self::error('两次密码不一致');
        }

        // 用 MD5 生成安全的缓存 key
        $cacheKey = 'email_code_' . md5($param['email']);
        $code = Cache::get($cacheKey);
        if ($code != $param['code']) {
            return self::error('验证码错误');
        }

        $resdep = $dep->firstByEmail($param['email']);
        if ($resdep) {
            return self::error('邮箱已存在');
        }

        // Get Default Role
        $defaultRole = $this->RoleDep->firstByDefault();
        $roleId = $defaultRole ? $defaultRole['id'] : 0; // 0 or handle error if strictly required

        // Create User
        $userData = [
            'email' => $param['email'],
            'password' => password_hash($param['password'], PASSWORD_DEFAULT),
            'username' => $param['username'],
            'role_id'  => $roleId,
        ];

        $userId = $dep->add($userData);

        // Create User Profile
        $profileData = [
            'user_id' => $userId,
            'avatar'  =>config('app.default_avatar',''),
            'sex' => SexEnum::UNKNOWN,
        ];
        $this->UserProfileDep->add($profileData);
        
        // Auto Login Logic
        $platformHeader = $request->header('platform', 'admin');
        $deviceId = $request->header('device-id', '');

        // Generate Tokens
        $tokens = TokenService::generateTokenPair();
        
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
        
        $sessionId = $sessionDep->add($sessionData);
        
        // 3. 更新 Redis 指针 (single 策略的核心)
        // cur_sess:{platform}:{userId} => sessionId
        // 无论 single=true/false 都更新，确保指针永远指向最新 Session
        // 设置 30 天 TTL，避免僵尸 Key 堆积
        $curSessKey = "cur_sess:" . strtolower(trim($platformHeader)) . ":{$userId}";
        Redis::connection('token')->set($curSessKey, $sessionId, 30 * 24 * 3600);
        
        // Log Login
        $logData = [
            'user_id' => $userId,
            'email' => $param['email'],
            'platform' => $platformHeader,
            'ip' => $request->getRealIp(),
            'ua' => $request->header('user-agent'),
            'success' => 1,
            'created_at' => $tokens['now']->toDateTimeString(),
        ];
        $logDep->add($logData);

        return self::response([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['access_ttl']
        ]);
    }


    public function login($request)
    {
        try {
            $param = $this->validate($request, UsersValidate::login());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $dep = $this->UserDep;
        $sessionDep = $this->UserSessionsDep;
        $logDep = $this->UsersLoginLogDep;

        $platformHeader = $request->header('platform', 'web');
        $deviceId = $request->header('device-id', '');

        $resDep = $dep->firstByEmail($param['email']);
        
        $logData = [
            'email' => $param['email'],
            'platform' => $platformHeader,
            'ip' => $request->getRealIp(),
            'ua' => $request->header('user-agent'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!$resDep) {
            $logData['success'] = 0;
            $logData['reason'] = '邮箱不存在';
            $logDep->add($logData);
            return self::error('账号或密码错误');
        }
        
        $logData['user_id'] = $resDep['id'];

        if (!password_verify($param['password'], $resDep['password'])) {
            $logData['success'] = 0;
            $logData['reason'] = '密码错误';
            $logDep->add($logData);
            return self::error('账号或密码错误');
        }

        // Generate Tokens
        $tokens = TokenService::generateTokenPair();

        // Check Policies: single_session_per_platform
        $policyConfig = config('auth.policies.' . ($platformHeader ?: 'default')) ?? config('auth.default_policy');
        
        // 1. 如果策略开启，先清理旧会话 (Revoke DB + Del Redis)
        // 这一步必须在 insert 之前做，否则会把新插入的也 revoke 掉
        if (!empty($policyConfig['single_session_per_platform'])) {
             $oldSessions = $sessionDep->listActiveByUserPlatform($resDep['id'], $platformHeader);
             foreach ($oldSessions as $oldSession) {
                 Redis::connection('token')->del($oldSession->access_token_hash);
             }
             $sessionDep->revokeByUserPlatform($resDep['id'], $platformHeader);
        }

        $sessionData = [
            'user_id' => $resDep['id'],
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
        
        // 2. 插入新会话
        $sessionId = $sessionDep->add($sessionData);
        
        // 3. 更新 Redis 指针 (single 策略的核心)
        // cur_sess:{platform}:{userId} => sessionId
        // 无论 single=true/false 都更新，确保指针永远指向最新 Session
        // 设置 30 天 TTL，避免僵尸 Key 堆积
        $curSessKey = "cur_sess:" . strtolower(trim($platformHeader)) . ":{$resDep['id']}";
        Redis::connection('token')->set($curSessKey, $sessionId, 30 * 24 * 3600);
        
        $logData['success'] = 1;
        $logDep->add($logData);

        return self::response([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['access_ttl']
        ]);
    }

    public function refresh($request)
    {
        $refreshToken = $request->post('refresh_token');
        if (!$refreshToken) {
            return self::error('缺少刷新令牌', 401);
        }

        try {
            $refreshHash = TokenService::hashToken($refreshToken);
        } catch (\Exception $e) {
            return self::error('令牌格式错误', 401);
        }

        $sessionDep = $this->UserSessionsDep;
        $session = $sessionDep->firstValidByRefreshHash($refreshHash);

        if (!$session) {
            return self::error('刷新令牌无效或已过期', 401);
        }

        // 核心修复：检查 Refresh Token 是否过期
        if (Carbon::parse($session['refresh_expires_at'])->isPast()) {
             return self::error('刷新令牌已过期，请重新登录', 401);
        }

        // 🛡️ 策略补漏：在刷新时也检查互踢策略
        // 场景：配置从 false 改为 true 后，如果不重新登录只刷新，需要在这里把其他端踢掉
        $platformHeader = $request->header('platform', 'web');
        $policyConfig = config('auth.policies.' . ($platformHeader ?: 'default')) ?? config('auth.default_policy');
        
        if (!empty($policyConfig['single_session_per_platform'])) {
             // 校验 Redis 指针
             $curSessKey = "cur_sess:" . strtolower(trim($platformHeader)) . ":{$session['user_id']}";
             $allowedSessionId = Redis::connection('token')->get($curSessKey);

             // 如果指针不存在，从 DB 重建
             if (!$allowedSessionId) {
                 $latest = $sessionDep->firstLatestActiveByUserPlatform($session['user_id'], $platformHeader);
                 if ($latest) {
                     $allowedSessionId = $latest->id;
                     Redis::connection('token')->set($curSessKey, $allowedSessionId, 30 * 24 * 3600);
                 }
             }

             // 如果指针存在，且不等于当前会话 ID，说明当前会话已被踢下线
             if ($allowedSessionId && (int)$allowedSessionId !== (int)$session['id']) {
                 return self::error('账号已在其他设备登录，请重新登录', 401);
             }
        }

        $tokens = TokenService::generateTokenPair();
        
        $data = [
            'access_token_hash' => $tokens['access_token_hash'],
            'refresh_token_hash' => $tokens['refresh_token_hash'],
            'expires_at' => $tokens['access_expires']->toDateTimeString(),
            // 关键：保持原有的 refresh_expires_at 不变（绝对过期策略），或者仅当需要延长时才更新
            // 这里我们选择：绝对不延长，严格遵守首次登录时的有效期
            'refresh_expires_at' => $session['refresh_expires_at'], 
            'last_seen_at' => $tokens['now']->toDateTimeString(),
            'ip' => $request->getRealIp(),
            'ua' => $request->header('user-agent'),
        ];
        
        // 保存旧的 access_token_hash 以便稍后删除缓存
        $oldAccessHash = $session['access_token_hash'] ?? null;

        $sessionDep->rotateById($session['id'], $data);
        
        // 删除旧 access_token 的 Redis 缓存，使其立即失效
        if (!empty($oldAccessHash)) {
            Redis::connection('token')->del($oldAccessHash);
        }

        // 刷新成功后，确保指针指向当前 Session（防止指针过期或意外丢失）
        // 同时续期 TTL 30 天
        $curSessKey = "cur_sess:" . strtolower(trim($platformHeader)) . ":{$session['user_id']}";
        Redis::connection('token')->set($curSessKey, $session['id'], 30 * 24 * 3600);
        
        return self::response([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['access_ttl']
        ]);
    }

    public function logout($request)
    {
        $bearer = $request->header('authorization');
        if ($bearer) {
            $token = str_replace('Bearer ', '', $bearer);
            try {
                $hash = TokenService::hashToken($token);
                $session = $this->UserSessionsDep->firstValidByAccessHash($hash);
                if ($session) {
                    $this->UserSessionsDep->revokeById($session['id']);
                    // Fix: Use hash as Redis key, matching CheckToken middleware
                    Redis::connection('token')->del($hash);
                }
            } catch (\Exception $e) {}
        }
        return self::response([], '退出成功');
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
            'avatar'   => $profile->avatar ?? 'https://zgm-1314542588.cos.ap-nanjing.myqcloud.com/defaultAvatar%2Favatar.jpg',
        ];

        // 把 user 直接传下去
        $perm = $this->buildPermissionContextByUser($user);

        Cache::set(
            'auth_perm_uid_' . $user->id,
            $perm['buttonCodes'],
            300
        );

        return self::success(array_merge($base, $perm));
    }


    /**
     * ⭐ 权限计算唯一事实源
     * 返回：menus / router / buttonCodes
     */
    private function buildPermissionContextByUser($user): array
    {
        $role = $this->RoleDep->first($user->role_id);
        $leafIds = json_decode($role->permission_id ?? '', true);

        if (empty($leafIds) || !is_array($leafIds)) {
            return [
                'permissions' => [],
                'router' => [],
                'buttonCodes' => [],
            ];
        }

        // 2️⃣ 所有“自身启用”的权限
        $allPerms = $this->PermissionDep->getAllPermissions();
        $permMap  = array_column($allPerms, null, 'id');

        // 3️⃣ 向上补齐父链
        $includeSet = [];
        foreach ($leafIds as $leafId) {
            $cur = (int)$leafId;
            while (isset($permMap[$cur]) && !isset($includeSet[$cur])) {
                $includeSet[$cur] = true;
                $parent = (int)$permMap[$cur]['parent_id'];
                if ($parent === -1 || !isset($permMap[$parent])) {
                    break;
                }
                $cur = $parent;
            }
        }

        // 4️⃣ 父链完整性校验（强关联）
        $isChainEnabled = function (int $id) use ($permMap): bool {
            $cur = $id;
            while (true) {
                if (!isset($permMap[$cur])) return false;
                $parent = (int)$permMap[$cur]['parent_id'];
                if ($parent === -1) return true;
                if (!isset($permMap[$parent])) return false;
                $cur = $parent;
            }
        };

        $enabledIds = array_values(array_filter(
            array_keys($includeSet),
            fn($id) => $isChainEnabled((int)$id)
        ));

        // 5️⃣ 菜单树（目录 + 页面）
        $menusData = array_filter($allPerms, fn($p) =>
            in_array($p['id'], $enabledIds, true) &&
            in_array($p['type'], [
                PermissionEnum::TYPE_DIR,
                PermissionEnum::TYPE_PAGE
            ])
        );
        $menus = $this->buildPermissionTree($menusData, -1);

        // 6️⃣ 前端路由（仅页面）
        $router = [];
        foreach ($menusData as $m) {
            if (
                $m['type'] == PermissionEnum::TYPE_PAGE &&
                !empty($m['path']) &&
                !empty($m['component'])
            ) {
                $router[] = [
                    'name' => 'menu_' . $m['id'],
                    'path' => $m['path'],
                    'component' => $m['component'],
                    'meta' => [
                        'menuId' => (string)$m['id'],
                    ],
                ];
            }
        }

        // 7️⃣ 🔥 按钮权限（最终事实源）
        $buttonCodes = [];
        foreach ($enabledIds as $id) {
            if (
                isset($permMap[$id]) &&
                $permMap[$id]['type'] === PermissionEnum::TYPE_BUTTON &&
                !empty($permMap[$id]['code'])
            ) {
                $buttonCodes[] = $permMap[$id]['code'];
            }
        }

        return [
            'permissions'  => $menus,
            'router'       => $router,
            'buttonCodes'  => array_values(array_unique($buttonCodes)),
        ];
    }

    private function buildPermissionTree(array $items, $parentId)
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $children = $this->buildPermissionTree($items, $item['id']);
                $node     = [
                    'index'    => (string)$item['id'],
                    'label'    => $item['name'],
                    'path'     => $item['path'],
                    'icon'     => $item['icon'],
                    'children' => [],
                    'i18n_key' => isset($item['i18n_key']) ? $item['i18n_key'] : ''
                ];
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }
        return $tree;
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
            'avatar' => $profile->avatar ?? 'https://zgm-1314542588.cos.ap-nanjing.myqcloud.com/defaultAvatar%2Favatar.jpg',
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
                'username'       => v::length(1, 64)->setName('用户名'),
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


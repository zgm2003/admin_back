<?php

namespace app\module\User;

//导入部分
use app\dep\AddressDep;
use app\dep\User\PermissionDep;
use app\dep\User\RoleDep;
use app\dep\User\UsersDep;
use app\dep\User\UsersTokenDep;
use app\dep\User\UserProfileDep;
use app\enum\CommonEnum;
use app\enum\EmailEnum;
use app\enum\PermissionEnum;
use app\enum\SexEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\ExportService;
use Carbon\Carbon;
use support\Cache;
use Webman\RedisQueue\Redis;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;
use app\validate\User\UsersValidate;

class UsersModule extends BaseModule
{

    public $UserDep;
    public $UserTokenDep;
    public $RoleDep;
    public $PermissionDep;
    public $AddressDep;
    public $UserProfileDep;

    public function __construct()
    {
        $this->UserDep = new UsersDep();
        $this->UserTokenDep = new UsersTokenDep();
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
        $tokenDep = $this->UserTokenDep;
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

        // 创建随机 token
        $token = md5(uniqid(rand(), true));
        // 设置 token 过期时间，一周后
        $expire_time = Carbon::now()->addWeek()->toDateTimeString();
        $data = [
            'email' => $param['email'],
            'password' => password_hash($param['password'], PASSWORD_DEFAULT),
            'username' => $param['username'],
        ];

        $userId = $dep->add($data);
        $tokenData = [
            'user_id' => $userId,
            'token' => $token,
            'expires_in' => $expire_time,
            'ip' => $request->getRealIp(),
        ];
        $tokenDep->add($tokenData);
        $data1 = [
            'token' => $token,
        ];
        return self::response($data1);
    }


    public function login($request)
    {
        try {
            $param = $this->validate($request, UsersValidate::login());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $dep = $this->UserDep;
        $tokenDep = $this->UserTokenDep;

        $resDep = $dep->firstByEmail($param['email']);
        if (!$resDep) {
            return self::error('邮箱不存在');
        }

        if (!password_verify($param['password'], $resDep['password'])) {
            return self::error('密码不正确');
        }

        //创建一个随机token,命名为$token
        $token = md5(uniqid(rand(), true));
        //创建token过期时间，为创建token的一周
        $expire_time = Carbon::now()->addWeek()->toDateTimeString();
        $data = [
            'token' => $token,
            'expires_in' => $expire_time,
            'ip' => $request->getRealIp(),
        ];

        $tokenDep->editByUserId($resDep['id'], $data);
        $data1 = [
            'token' => $token,
        ];
        return self::response($data1);
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
        Redis::send($queue, $data);

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


<?php

namespace app\module\User;

use app\dep\Permission\PermissionDep;
use app\dep\Permission\RoleDep;
use app\dep\User\UserProfileDep;
use app\dep\User\UsersDep;
use app\dep\User\UsersQuickEntryDep;
use app\enum\CommonEnum;
use app\enum\SystemEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\User\PermissionService;
use app\validate\User\UsersValidate;
use support\Cache;

/**
 * 用户个人信息模块
 * 负责：初始化、个人信息查看/编辑、更新手机号/邮箱/密码
 */
class ProfileModule extends BaseModule
{
    protected UsersDep $usersDep;
    protected RoleDep $roleDep;
    protected PermissionDep $permissionDep;
    protected UserProfileDep $userProfileDep;
    protected UsersQuickEntryDep $usersQuickEntryDep;
    protected PermissionService $permissionService;
    protected DictService $dictService;

    public function __construct()
    {
        $this->usersDep = $this->dep(UsersDep::class);
        $this->roleDep = $this->dep(RoleDep::class);
        $this->permissionDep = $this->dep(PermissionDep::class);
        $this->userProfileDep = $this->dep(UserProfileDep::class);
        $this->usersQuickEntryDep = $this->dep(UsersQuickEntryDep::class);
        $this->permissionService = $this->svc(PermissionService::class);
        $this->dictService = $this->svc(DictService::class);
    }

    /**
     * 初始化（获取当前用户基本信息和权限）
     */
    public function init($request): array
    {
        $user = $this->usersDep->find($request->userId);
        self::throwNotFound($user, '用户不存在');

        $profile = $this->userProfileDep->findByUserId($user->id);
        $role = $user->role_id ? $this->roleDep->find($user->role_id) : null;

        $base = [
            'user_id' => $user->id,
            'username' => $user->username,
            'avatar' => $profile->avatar ?? '',
            'role_name' => $role->name ?? '',
        ];

        // 根据平台过滤权限（admin/app，已由 CheckToken 校验）
        $platform = $request->platform;
        $perm = $this->permissionService->buildPermissionContextByUser($user, $platform);

        // 按钮权限缓存key按平台隔离
        $cacheKey = 'auth_perm_uid_' . $user->id . '_' . $platform;
        Cache::set($cacheKey, $perm['buttonCodes'], 300);

        // 获取用户快捷入口配置
        $quickEntry = $this->usersQuickEntryDep->listByUserId($user->id);

        return self::success(array_merge($base, $perm, ['quick_entry' => $quickEntry]));
    }

    /**
     * 获取个人信息详情
     */
    public function initPersonal($request): array
    {
        $param = $this->validate($request, UsersValidate::initPersonal());

        $user = $this->usersDep->find($param['user_id']);
        self::throwNotFound($user, '用户不存在');

        $profile = $this->userProfileDep->findByUserId($user->id);
        $resRole = $this->roleDep->find($user->role_id);

        $data['list'] = [
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $profile->avatar ?? '',
            'phone' => $user->phone,
            'role_id' => $user->role_id,
            'role_name' => $resRole['name'] ?? '',
            'address' => (int)($profile->address_id ?? 0),
            'detail_address' => $profile->detail_address ?? '',
            'sex' => (int)($profile->sex ?? 0),
            'birthday' => $profile->birthday ?? '',
            'bio' => $profile->bio ?? '',
            'is_self' => $param['user_id'] == $request->userId ? CommonEnum::YES : CommonEnum::NO,
            'has_password' => !empty($user->password),
        ];

        $data['dict'] = $this->dictService
            ->setAuthAdressTree()
            ->setSexArr()
            ->setVerifyTypeArr()
            ->getDict();

        return self::success($data);
    }

    /**
     * 编辑个人信息
     */
    public function editPersonal($request): array
    {
        $param = $this->validate($request, UsersValidate::editPersonal());

        $user = $this->usersDep->find($request->userId);
        self::throwNotFound($user, '用户不存在');

        $userData = [
            'username' => $param['username'],
        ];

        $profileData = [
            'avatar' => $param['avatar'] ?? null,
            'sex' => (int)$param['sex'],
            'birthday' => $param['birthday'] ?? null,
            'address_id' => (int)$param['address'],
            'detail_address' => $param['detail_address'] ?? '',
            'bio' => $param['bio'] ?? '',
        ];

        $this->usersDep->update($user->id, $userData);
        $this->userProfileDep->updateByUserId($user->id, $profileData);

        return self::success();
    }

    /**
     * 更新手机号（需验证码）
     */
    public function updatePhone($request): array
    {
        $param = $this->validate($request, UsersValidate::updatePhone());

        $phone = $param['phone'];
        self::throwIf(!isValidPhone($phone), '手机号格式不正确');

        $cacheKey = 'phone_code_' . md5($phone);
        $code = Cache::get($cacheKey);
        self::throwIf(!$code || $code != $param['code'], '验证码错误或已失效');

        $exists = $this->usersDep->findByPhone($phone);
        self::throwIf($exists && $exists['id'] != $request->userId, '该手机号已被其他账号绑定');

        $this->usersDep->update($request->userId, ['phone' => $phone]);
        Cache::delete($cacheKey);

        return self::success([], '手机号绑定成功');
    }

    /**
     * 更新邮箱（需验证码）
     */
    public function updateEmail($request): array
    {
        $param = $this->validate($request, UsersValidate::updateEmail());

        $email = $param['email'];

        $cacheKey = 'email_code_' . md5($email);
        $code = Cache::get($cacheKey);
        self::throwIf(!$code || $code != $param['code'], '验证码错误或已失效');

        $exists = $this->usersDep->findByEmail($email);
        self::throwIf($exists && $exists['id'] != $request->userId, '该邮箱已被其他账号绑定');

        $this->usersDep->update($request->userId, ['email' => $email]);
        Cache::delete($cacheKey);

        return self::success([], '邮箱绑定成功');
    }

    /**
     * 更新密码
     * 支持两种验证方式：原密码验证 / 验证码验证
     */
    public function updatePassword($request): array
    {
        $param = $this->validate($request, UsersValidate::updatePassword());

        $user = $this->usersDep->find($request->userId);
        self::throwNotFound($user, '用户不存在');

        $verifyType = $param['verify_type'];

        self::throwIf($param['new_password'] !== $param['confirm_password'], '两次输入的密码不一致');

        // 验证身份
        if ($verifyType === SystemEnum::VERIFY_TYPE_PASSWORD) {
            self::throwIf(empty($param['old_password']), '请输入原密码');
            self::throwIf(empty($user->password), '您尚未设置密码，请使用验证码方式');
            self::throwIf(!password_verify($param['old_password'], $user->password), '原密码错误');
        } else {
            self::throwIf(empty($param['code']), '请输入验证码');

            $account = $user->email ?: $user->phone;
            self::throwIf(!$account, '请先绑定邮箱或手机号');

            $cacheKey = isValidEmail($account)
                ? 'email_code_' . md5($account)
                : 'phone_code_' . md5($account);

            $code = Cache::get($cacheKey);
            self::throwIf(!$code || $code != $param['code'], '验证码错误或已失效');
            Cache::delete($cacheKey);
        }

        // 防止新密码与原密码相同
        self::throwIf(
            !empty($user->password) && password_verify($param['new_password'], $user->password),
            '新密码不能与原密码相同'
        );

        $this->usersDep->update($user->id, [
            'password' => password_hash($param['new_password'], PASSWORD_DEFAULT)
        ]);

        return self::success([], '密码设置成功');
    }

    /**
     * 旧版修改密码（兼容）
     * @deprecated 使用 updatePassword 代替
     */
    public function editPassword($request): array
    {
        $param = $this->validate($request, UsersValidate::editPassword());

        $user = $this->usersDep->find($request->userId);
        self::throwNotFound($user, '用户不存在');
        self::throwIf(!password_verify($param['password'], $user->password), '原密码不正确');
        self::throwIf($param['newpassword'] !== $param['respassword'], '新密码不一致');
        self::throwIf(password_verify($param['newpassword'], $user->password), '新密码不能与原密码一致');

        $this->usersDep->update($user->id, [
            'password' => password_hash($param['newpassword'], PASSWORD_DEFAULT),
        ]);

        return self::success([], '密码修改成功');
    }
}

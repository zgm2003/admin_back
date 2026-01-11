<?php

namespace app\module\User;

use app\dep\User\PermissionDep;
use app\dep\User\RoleDep;
use app\dep\User\UserProfileDep;
use app\dep\User\UsersDep;
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
    protected PermissionService $permissionService;

    public function __construct()
    {
        $this->usersDep = new UsersDep();
        $this->roleDep = new RoleDep();
        $this->permissionDep = new PermissionDep();
        $this->userProfileDep = new UserProfileDep();
        $this->permissionService = new PermissionService();
    }

    /**
     * 初始化（获取当前用户基本信息和权限）
     */
    public function init($request): array
    {
        $user = $this->usersDep->find($request->userId);
        if (!$user) {
            return self::error('用户不存在');
        }

        $profile = $this->userProfileDep->findByUserId($user->id);

        $base = [
            'user_id' => $user->id,
            'username' => $user->username,
            'avatar' => $profile->avatar ?? '',
        ];

        $perm = $this->permissionService->buildPermissionContextByUser($user);

        Cache::set('auth_perm_uid_' . $user->id, $perm['buttonCodes'], 300);

        return self::success(array_merge($base, $perm));
    }

    /**
     * 获取个人信息详情
     */
    public function initPersonal($request): array
    {
        $param = $request->all();
        $dictService = new DictService();

        $user = $this->usersDep->find($param['user_id']);
        if (!$user) {
            return self::error('用户不存在');
        }

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

        $data['dict'] = $dictService
            ->setAuthAdressTree()
            ->setSexArr()
            ->setVerifyTypeArr()
            ->getDict();

        return self::response($data);
    }

    /**
     * 编辑个人信息
     */
    public function editPersonal($request): array
    {
        try {
            $param = $this->validate($request, UsersValidate::editPersonal());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $user = $this->usersDep->find($request->userId);
        if (!$user) {
            return self::error('用户不存在');
        }

        if (isset($param['phone']) && trim((string)$param['phone']) !== '' && !is_valid_phone_number($param['phone'])) {
            return self::error('无效的手机号码');
        }

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

        $this->usersDep->update($user->id, $userData);
        $this->userProfileDep->updateByUserId($user->id, $profileData);

        return self::response();
    }

    /**
     * 更新手机号（需验证码）
     */
    public function updatePhone($request): array
    {
        try {
            $param = $this->validate($request, UsersValidate::updatePhone());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $phone = $param['phone'];

        if (!is_valid_phone_number($phone)) {
            return self::error('手机号格式不正确');
        }

        $cacheKey = 'phone_code_' . md5($phone);
        $code = Cache::get($cacheKey);
        if (!$code || $code != $param['code']) {
            return self::error('验证码错误或已失效');
        }

        $exists = $this->usersDep->findByPhone($phone);
        if ($exists && $exists['id'] != $request->userId) {
            return self::error('该手机号已被其他账号绑定');
        }

        $this->usersDep->update($request->userId, ['phone' => $phone]);
        Cache::delete($cacheKey);

        return self::response([], '手机号绑定成功');
    }

    /**
     * 更新邮箱（需验证码）
     */
    public function updateEmail($request): array
    {
        try {
            $param = $this->validate($request, UsersValidate::updateEmail());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $email = $param['email'];

        $cacheKey = 'email_code_' . md5($email);
        $code = Cache::get($cacheKey);
        if (!$code || $code != $param['code']) {
            return self::error('验证码错误或已失效');
        }

        $exists = $this->usersDep->findByEmail($email);
        if ($exists && $exists['id'] != $request->userId) {
            return self::error('该邮箱已被其他账号绑定');
        }

        $this->usersDep->update($request->userId, ['email' => $email]);
        Cache::delete($cacheKey);

        return self::response([], '邮箱绑定成功');
    }

    /**
     * 更新密码
     * 支持两种验证方式：原密码验证 / 验证码验证
     */
    public function updatePassword($request): array
    {
        try {
            $param = $this->validate($request, UsersValidate::updatePassword());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $user = $this->usersDep->find($request->userId);
        if (!$user) {
            return self::error('用户不存在');
        }

        $verifyType = $param['verify_type'];

        if ($param['new_password'] !== $param['confirm_password']) {
            return self::error('两次输入的密码不一致');
        }

        // 验证身份
        if ($verifyType === SystemEnum::VERIFY_TYPE_PASSWORD) {
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
            if (empty($param['code'])) {
                return self::error('请输入验证码');
            }

            $account = $user->email ?: $user->phone;
            if (!$account) {
                return self::error('请先绑定邮箱或手机号');
            }

            $cacheKey = isValidEmail($account)
                ? 'email_code_' . md5($account)
                : 'phone_code_' . md5($account);

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

        $this->usersDep->update($user->id, [
            'password' => password_hash($param['new_password'], PASSWORD_DEFAULT)
        ]);

        return self::response([], '密码设置成功');
    }

    /**
     * 旧版修改密码（兼容）
     * @deprecated 使用 updatePassword 代替
     */
    public function editPassword($request): array
    {
        try {
            $param = $this->validate($request, UsersValidate::editPassword());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $user = $this->usersDep->find($request->userId);
        if (!$user) {
            return self::error('用户不存在');
        }

        if (!password_verify($param['password'], $user->password)) {
            return self::error('原密码不正确');
        }

        if ($param['newpassword'] !== $param['respassword']) {
            return self::error('新密码不一致');
        }

        if (password_verify($param['newpassword'], $user->password)) {
            return self::error('新密码不能与原密码一致');
        }

        $this->usersDep->update($user->id, [
            'password' => password_hash($param['newpassword'], PASSWORD_DEFAULT),
        ]);

        return self::response([], '密码修改成功');
    }
}

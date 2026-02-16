<?php

namespace app\module\User;

use app\dep\Permission\RoleDep;
use app\dep\User\UserProfileDep;
use app\dep\User\UsersDep;
use app\dep\User\UsersQuickEntryDep;
use app\enum\CommonEnum;
use app\enum\CacheTTLEnum;
use app\enum\SystemEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\User\PermissionService;
use app\service\User\VerifyCodeService;
use app\validate\User\UsersValidate;
use support\Cache;

/**
 * 用户个人信息模块
 * 负责：初始化、个人信息查看/编辑、更新手机号/邮箱/密码
 */
class ProfileModule extends BaseModule
{
    /**
     * 初始化（获取当前用户基本信息和权限）
     */
    public function init($request): array
    {
        $user = $this->dep(UsersDep::class)->findOrFail($request->userId);

        $profile = $this->dep(UserProfileDep::class)->findByUserId($user->id);
        $role = $user->role_id ? $this->dep(RoleDep::class)->find($user->role_id) : null;

        $base = [
            'user_id'   => $user->id,
            'username'  => $user->username,
            'avatar'    => $profile->avatar ?? '',
            'role_name' => $role->name ?? '',
        ];

        $platform = $request->platform;
        $perm = $this->svc(PermissionService::class)->buildPermissionContextByUser($user, $platform);

        Cache::set("auth_perm_uid_{$user->id}_{$platform}", $perm['buttonCodes'], CacheTTLEnum::PERMISSION_BUTTONS);

        $quickEntry = $this->dep(UsersQuickEntryDep::class)->listByUserId($user->id);

        return self::success([...$base, ...$perm, 'quick_entry' => $quickEntry]);
    }

    /**
     * 获取个人信息详情
     */
    public function initPersonal($request): array
    {
        $param = $this->validate($request, UsersValidate::initPersonal());

        $user = $this->dep(UsersDep::class)->findOrFail($param['user_id']);
        $profile = $this->dep(UserProfileDep::class)->findByUserId($user->id);
        $role = $this->dep(RoleDep::class)->find($user->role_id);

        $data['list'] = [
            'username'       => $user->username,
            'email'          => $user->email,
            'avatar'         => $profile->avatar ?? '',
            'phone'          => $user->phone,
            'role_id'        => $user->role_id,
            'role_name'      => $role['name'] ?? '',
            'address'        => (int)($profile->address_id ?? 0),
            'detail_address' => $profile->detail_address ?? '',
            'sex'            => (int)($profile->sex ?? 0),
            'birthday'       => $profile->birthday ?? '',
            'bio'            => $profile->bio ?? '',
            'is_self'        => $param['user_id'] == $request->userId ? CommonEnum::YES : CommonEnum::NO,
            'has_password'   => !empty($user->password),
        ];

        $data['dict'] = $this->svc(DictService::class)
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

        $user = $this->dep(UsersDep::class)->findOrFail($request->userId);

        $this->dep(UsersDep::class)->update($user->id, [
            'username' => $param['username'],
        ]);

        $this->dep(UserProfileDep::class)->updateByUserId($user->id, [
            'avatar'         => $param['avatar'] ?? null,
            'sex'            => (int)$param['sex'],
            'birthday'       => $param['birthday'] ?? null,
            'address_id'     => (int)$param['address'],
            'detail_address' => $param['detail_address'] ?? '',
            'bio'            => $param['bio'] ?? '',
        ]);

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
        self::throwUnless(VerifyCodeService::verify($phone, $param['code'], 'bind_phone'), '验证码错误或已失效');

        $exists = $this->dep(UsersDep::class)->findByPhone($phone);
        self::throwIf($exists && $exists['id'] != $request->userId, '该手机号已被其他账号绑定');

        $this->dep(UsersDep::class)->update($request->userId, ['phone' => $phone]);

        return self::success([], '手机号绑定成功');
    }

    /**
     * 更新邮箱（需验证码）
     */
    public function updateEmail($request): array
    {
        $param = $this->validate($request, UsersValidate::updateEmail());

        $email = $param['email'];
        self::throwUnless(VerifyCodeService::verify($email, $param['code'], 'bind_email'), '验证码错误或已失效');

        $exists = $this->dep(UsersDep::class)->findByEmail($email);
        self::throwIf($exists && $exists['id'] != $request->userId, '该邮箱已被其他账号绑定');

        $this->dep(UsersDep::class)->update($request->userId, ['email' => $email]);

        return self::success([], '邮箱绑定成功');
    }

    /**
     * 更新密码
     * 支持两种验证方式：原密码验证 / 验证码验证
     */
    public function updatePassword($request): array
    {
        $param = $this->validate($request, UsersValidate::updatePassword());

        $user = $this->dep(UsersDep::class)->findOrFail($request->userId);

        self::throwIf($param['new_password'] !== $param['confirm_password'], '两次输入的密码不一致');

        // 验证身份
        if ($param['verify_type'] === SystemEnum::VERIFY_TYPE_PASSWORD) {
            self::throwIf(empty($param['old_password']), '请输入原密码');
            self::throwIf(empty($user->password), '您尚未设置密码，请使用验证码方式');
            self::throwIf(!password_verify($param['old_password'], $user->password), '原密码错误');
        } else {
            self::throwIf(empty($param['code']), '请输入验证码');

            $account = $user->email ?: $user->phone;
            self::throwUnless($account, '请先绑定邮箱或手机号');
            self::throwUnless(
                VerifyCodeService::verify($account, $param['code'], 'change_password'),
                '验证码错误或已失效'
            );
        }

        // 防止新密码与原密码相同
        self::throwIf(
            !empty($user->password) && password_verify($param['new_password'], $user->password),
            '新密码不能与原密码相同'
        );

        $this->dep(UsersDep::class)->update($user->id, [
            'password' => password_hash($param['new_password'], PASSWORD_DEFAULT),
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

        $user = $this->dep(UsersDep::class)->findOrFail($request->userId);
        self::throwIf(!password_verify($param['password'], $user->password), '原密码不正确');
        self::throwIf($param['newpassword'] !== $param['respassword'], '新密码不一致');
        self::throwIf(password_verify($param['newpassword'], $user->password), '新密码不能与原密码一致');

        $this->dep(UsersDep::class)->update($user->id, [
            'password' => password_hash($param['newpassword'], PASSWORD_DEFAULT),
        ]);

        return self::success([], '密码修改成功');
    }
}

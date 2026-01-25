<?php

namespace app\dep\User;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\User\UsersModel;
use support\Model;

class UsersDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UsersModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据邮箱查询
     */
    public function findByEmail(string $email)
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * 根据手机号查询
     */
    public function findByPhone(string $phone)
    {
        return $this->model->where('phone', $phone)->first();
    }

    /**
     * 根据用户名查询
     */
    public function findByUsername(string $username)
    {
        return $this->model->where('username', $username)->first();
    }

    /**
     * 根据账号查询（邮箱/用户名/手机号）
     */
    public function findByAccount(string $account)
    {
        return $this->model
            ->where('email', $account)
            ->orWhere('username', $account)
            ->orWhere('phone', $account)
            ->first();
    }

    /**
     * 根据角色ID获取用户ID列表
     */
    public function getIdsByRoleIds(array $roleIds)
    {
        return $this->model->whereIn('role_id', $roleIds)->pluck('id');
    }

    /**
     * 获取所有用户（字典用）
     */
    public function all()
    {
        return $this->model->select(['id', 'username', 'email'])->get();
    }

    /**
     * 获取用户总数
     */
    public function countAll(): int
    {
        return $this->model->where('is_del', CommonEnum::NO)->count();
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页 + 过滤，关联 profile）
     */
    public function list(array $param)
    {
        return $this->model
            ->from('users as u')
            ->leftJoin('user_profiles as up', 'u.id', '=', 'up.user_id')
            ->where('u.is_del', CommonEnum::NO)
            // keyword 模糊搜索（用户名/邮箱/手机号）- 右模糊，保留索引
            ->when(isset($param['keyword']) && $param['keyword'] !== '', fn($q) => $q->where(function ($q) use ($param) {
                $kw = $param['keyword'] . '%';
                $q->where('u.username', 'like', $kw)
                  ->orWhere('u.email', 'like', $kw)
                  ->orWhere('u.phone', 'like', $kw);
            }))
            ->when(isset($param['username']) && $param['username'] !== '', fn($q) => $q->where('u.username', 'like', $param['username'] . '%'))
            ->when(isset($param['email']) && $param['email'] !== '', fn($q) => $q->where('u.email', 'like', $param['email'] . '%'))
            ->when(isset($param['detail_address']) && $param['detail_address'] !== '', fn($q) => $q->where('up.detail_address', 'like', $param['detail_address'] . '%'))
            ->when(!empty($param['address_id'] ?? $param['address'] ?? null), function ($q) use ($param) {
                $ids = $param['address_id'] ?? $param['address'];
                if (is_array($ids)) {
                    $q->whereIn('up.address_id', array_map('intval', $ids));
                } else {
                    $q->where('up.address_id', (int)$ids);
                }
            })
            ->when(isset($param['role_id']) && $param['role_id'] !== '', fn($q) => $q->where('u.role_id', (int)$param['role_id']))
            ->when(isset($param['sex']) && $param['sex'] !== '', fn($q) => $q->where('up.sex', (int)$param['sex']))
            ->when(!empty($param['date']) && is_array($param['date']) && count($param['date']) === 2, fn($q) => $q->whereBetween('u.created_at', [$param['date'][0], $param['date'][1]]))
            ->select([
                'u.id', 'u.username', 'u.email', 'u.phone', 'u.role_id', 'u.status', 'u.created_at', 'u.updated_at',
                'up.avatar', 'up.sex', 'up.address_id', 'up.detail_address', 'up.bio',
            ])
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }
}

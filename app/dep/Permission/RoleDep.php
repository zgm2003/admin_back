<?php

namespace app\dep\Permission;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Permission\RoleModel;
use support\Model;

class RoleDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new RoleModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据名称查询
     */
    public function findByName(string $name)
    {
        return $this->model
            ->where('name', $name)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 获取超级管理员角色
     */
    public function getSuperAdmin()
    {
        return $this->findByName('超级管理员');
    }

    /**
     * 获取管理员角色
     */
    public function getAdmin()
    {
        return $this->findByName('管理员');
    }

    /**
     * 获取默认角色
     */
    public function getDefault()
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('is_default', CommonEnum::YES)
            ->first();
    }

    /**
     * 获取所有有效角色
     */
    public function allActive()
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->get();
    }

    /**
     * 检查指定 ID 中是否包含默认角色
     */
    public function hasDefaultIn(array $ids): bool
    {
        return $this->model
            ->whereIn('id', $ids)
            ->where('is_del', CommonEnum::NO)
            ->where('is_default', CommonEnum::YES)
            ->exists();
    }

    // ==================== 写入方法 ====================

    /**
     * 清除所有默认角色标记
     */
    public function clearDefault(): int
    {
        return $this->model
            ->where('is_default', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_default' => CommonEnum::NO]);
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['name']), fn($q) => $q->where('name', 'like', "%{$param['name']}%"))
            ->orderBy('id', 'asc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }
}

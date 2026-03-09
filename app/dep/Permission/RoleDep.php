<?php

namespace app\dep\Permission;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Permission\RoleModel;
use support\Model;
use RuntimeException;

class RoleDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new RoleModel();
    }


    public function add(array $data): int
    {
        return parent::add($this->normalizePermissionPayload($data));
    }

    public function update($ids, array $data): int
    {
        return parent::update($ids, $this->normalizePermissionPayload($data));
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
     * 检查名称是否已存在（排除指定 ID）
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        return $this->model
            ->where('name', $name)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
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

    private function normalizePermissionPayload(array $data): array
    {
        if (!array_key_exists('permission_id', $data) || !is_array($data['permission_id'])) {
            return $data;
        }

        $data['permission_id'] = $this->encodePermissionIds($data['permission_id']);

        return $data;
    }

    private function encodePermissionIds(array $permissionIds): string
    {
        $normalized = array_values(array_unique(array_filter(
            array_map('intval', $permissionIds),
            static fn(int $id) => $id > 0
        )));

        $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('permission_id \u7f16\u7801\u5931\u8d25');
        }

        return $payload;
    }
}

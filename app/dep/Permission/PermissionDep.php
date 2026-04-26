<?php

namespace app\dep\Permission;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\model\Permission\PermissionModel;
use support\Cache;
use support\Model;

class PermissionDep extends BaseDep
{
    const CACHE_KEY_ALL = 'perm_all_permissions_v20260426_rbac_editor_metadata';

    protected function createModel(): Model
    {
        return new PermissionModel();
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
     * 根据路径查询
     */
    public function findByPath(string $path)
    {
        return $this->model
            ->where('path', $path)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 根据平台+code查询（唯一性检查）
     */
    public function findByPlatformCode(string $platform, string $code, ?int $excludeId = null)
    {
        return $this->model
            ->where('platform', $platform)
            ->where('code', $code)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }

    /**
     * 检查平台+code是否已存在
     */
    public function existsByPlatformCode(string $platform, string $code, ?int $excludeId = null): bool
    {
        return $this->model
            ->where('platform', $platform)
            ->where('code', $code)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * 根据平台+path查询（唯一性检查）
     */
    public function findByPlatformPath(string $platform, string $path, ?int $excludeId = null)
    {
        return $this->model
            ->where('platform', $platform)
            ->where('path', $path)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }

    /**
     * 检查平台+path是否已存在
     */
    public function existsByPlatformPath(string $platform, string $path, ?int $excludeId = null): bool
    {
        return $this->model
            ->where('platform', $platform)
            ->where('path', $path)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * 根据平台+i18n_key查询（唯一性检查）
     */
    public function findByPlatformI18nKey(string $platform, string $i18nKey, ?int $excludeId = null)
    {
        return $this->model
            ->where('platform', $platform)
            ->where('i18n_key', $i18nKey)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }

    /**
     * 检查平台+i18n_key是否已存在
     */
    public function existsByPlatformI18nKey(string $platform, string $i18nKey, ?int $excludeId = null): bool
    {
        return $this->model
            ->where('platform', $platform)
            ->where('i18n_key', $i18nKey)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * 根据ID查找平台（用于校验parent_id同平台）
     */
    public function getPlatformById(int $id): ?string
    {
        $row = $this->model->where('id', $id)->where('is_del', CommonEnum::NO)->first();
        return $row ? $row->platform : null;
    }

    /**
     * 根据父分类名称查询
     */
    public function findByParentCategory(string $name)
    {
        return $this->model
            ->where('name', $name)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 根据父ID和名称查询子分类
     */
    public function findByChildCategory(int $parentId, string $name)
    {
        return $this->model
            ->where('name', $name)
            ->where('parent_id', $parentId)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 获取所有顶级权限
     */
    public function allParent()
    {
        return $this->model
            ->where('parent_id', PermissionEnum::ROOT_PARENT_ID)
            ->where('is_del', CommonEnum::NO)
            ->get();
    }

    /**
     * 获取所有有效权限
     */
    public function allActive()
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->get();
    }

    /**
     * 根据 ID 列表获取路由权限
     */
    public function getRouterByIds(array $ids)
    {
        return $this->model
            ->whereIn('id', $ids)
            ->whereNotNull('path')
            ->whereNotNull('component')
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->get();
    }


    /**
     * 检查是否存在未包含在当前删除集合中的子节点
     */
    public function hasChildrenOutsideIds(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        return $this->model
            ->whereIn('parent_id', $ids)
            ->whereNotIn('id', $ids)
            ->where('is_del', CommonEnum::NO)
            ->exists();
    }

    /**
     * 获取指定权限及其所有未删除后代节点 ID。
     */
    public function getCascadeIds(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return [];
        }

        $childrenByParent = [];
        $rows = $this->model
            ->where('is_del', CommonEnum::NO)
            ->get(['id', 'parent_id']);

        foreach ($rows as $row) {
            $parentId = (int)$row->parent_id;
            $childrenByParent[$parentId][] = (int)$row->id;
        }

        $includedIdMap = [];
        $stack = $ids;

        while (!empty($stack)) {
            $currentId = (int)array_pop($stack);
            if ($currentId <= 0 || isset($includedIdMap[$currentId])) {
                continue;
            }

            $includedIdMap[$currentId] = true;
            foreach ($childrenByParent[$currentId] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return array_keys($includedIdMap);
    }

    /**
     * 获取指定父节点的所有有效子节点。
     */
    public function getActiveChildrenByParentId(int $parentId)
    {
        return $this->model
            ->where('parent_id', $parentId)
            ->where('is_del', CommonEnum::NO)
            ->get(['id', 'type', 'platform']);
    }

    /**
     * 获取所有权限（带缓存）
     */
    public function getAllPermissions(): array
    {
        $cached = Cache::get(self::CACHE_KEY_ALL);
        if ($cached !== null) {
            return $cached;
        }

        $permissions = $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->orderBy('parent_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->toArray();

        Cache::set(self::CACHE_KEY_ALL, $permissions); // 永久缓存

        return $permissions;
    }

    /**
     * 清除权限缓存
     */
    public static function clearCache(): void
    {
        Cache::delete(self::CACHE_KEY_ALL);
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（树形结构用）
     */
    public function list(array $param)
    {
        return $this->model
            ->where('platform', $param['platform'])
            ->when(!empty($param['name']), fn($q) => $q->where('name', 'like', "%{$param['name']}%"))
            ->when(!empty($param['path']), fn($q) => $q->where('path', 'like', "%{$param['path']}%"))
            ->when(!empty($param['type']), fn($q) => $q->where('type', $param['type']))
            ->where('is_del', CommonEnum::NO)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }
}

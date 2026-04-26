<?php

namespace app\dep\Permission;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\model\Permission\RolePermissionModel;
use support\Model;

class RolePermissionDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new RolePermissionModel();
    }

    public function getPermissionIdsByRoleId(int $roleId): array
    {
        return $this->getPermissionIdsByRoleIds([$roleId])[$roleId] ?? [];
    }

    public function getPermissionIdsByRoleIds(array $roleIds): array
    {
        $roleIds = $this->normalizeIds($roleIds);
        if (empty($roleIds)) {
            return [];
        }

        $rows = $this->model
            ->whereIn('role_id', $roleIds)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'asc')
            ->get(['role_id', 'permission_id']);

        $map = [];
        foreach ($rows as $row) {
            $roleId = (int)$row->role_id;
            $map[$roleId][] = (int)$row->permission_id;
        }

        return $map;
    }

    public function getRoleIdsByPermissionIds(array $permissionIds): array
    {
        $permissionIds = $this->normalizeIds($permissionIds);
        if (empty($permissionIds)) {
            return [];
        }

        return $this->model
            ->whereIn('permission_id', $permissionIds)
            ->where('is_del', CommonEnum::NO)
            ->pluck('role_id')
            ->map(static fn($id) => (int)$id)
            ->unique()
            ->values()
            ->toArray();
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        if ($roleId <= 0) {
            return;
        }

        $permissionIds = $this->normalizeAssignablePermissionIdsWithPageParents($permissionIds);

        $current = $this->model
            ->where('role_id', $roleId)
            ->where('is_del', CommonEnum::NO)
            ->pluck('permission_id')
            ->map(static fn($id) => (int)$id)
            ->toArray();

        $toAdd = array_diff($permissionIds, $current);
        $toRemove = array_diff($current, $permissionIds);

        foreach ($toAdd as $permissionId) {
            $this->bindOrRestore($roleId, (int)$permissionId);
        }

        if (!empty($toRemove)) {
            $this->model
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $toRemove)
                ->where('is_del', CommonEnum::NO)
                ->update(['is_del' => CommonEnum::YES]);
        }
    }

    public function deleteByRoleIds(array $roleIds): int
    {
        $roleIds = $this->normalizeIds($roleIds);
        if (empty($roleIds)) {
            return 0;
        }

        return $this->model
            ->whereIn('role_id', $roleIds)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }

    private function bindOrRestore(int $roleId, int $permissionId): int
    {
        $binding = $this->model
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->first();

        if ($binding) {
            if ((int)$binding->is_del === CommonEnum::NO) {
                return 0;
            }

            return $this->model
                ->where('id', (int)$binding->id)
                ->update(['is_del' => CommonEnum::NO]);
        }

        $this->add([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'is_del' => CommonEnum::NO,
        ]);

        return 1;
    }

    protected function permissionDep(): PermissionDep
    {
        return new PermissionDep();
    }

    public function filterToActiveAssignablePermissionIds(array $permissionIds): array
    {
        return $this->normalizeAssignablePermissionIdsWithPageParents($permissionIds);
    }

    /**
     * Normalize role assignments to the real RBAC contract:
     * - DIR is a menu container, never persisted as a grant.
     * - PAGE is an explicit page/view grant.
     * - BUTTON is an operation grant and always implies its parent PAGE when present.
     */
    public function normalizeAssignablePermissionIdsWithPageParents(array $permissionIds): array
    {
        $permissionIds = $this->normalizeIds($permissionIds);
        if (empty($permissionIds)) {
            return [];
        }

        $permissionMap = $this->activePermissionMap();
        $normalizedIdMap = [];

        foreach ($permissionIds as $permissionId) {
            $permission = $permissionMap[$permissionId] ?? null;
            if ($permission === null) {
                continue;
            }

            if ($permission['type'] === PermissionEnum::TYPE_PAGE) {
                $normalizedIdMap[$permissionId] = true;
                continue;
            }

            if ($permission['type'] === PermissionEnum::TYPE_BUTTON) {
                $normalizedIdMap[$permissionId] = true;

                $parentId = $permission['parent_id'];
                if (($permissionMap[$parentId]['type'] ?? null) === PermissionEnum::TYPE_PAGE) {
                    $normalizedIdMap[$parentId] = true;
                }
            }
        }

        $ids = array_keys($normalizedIdMap);
        sort($ids);

        return $ids;
    }

    public function filterToActiveLeafPermissionIds(array $permissionIds): array
    {
        return $this->filterToActiveAssignablePermissionIds($permissionIds);
    }

    protected function filterActiveAssignablePermissionIds(array $permissionIds): array
    {
        return $this->filterToActiveAssignablePermissionIds($permissionIds);
    }

    /**
     * Build an active permission lookup from the permanent permission metadata cache.
     */
    private function activePermissionMap(): array
    {
        $map = [];

        foreach ($this->permissionDep()->getAllPermissions() as $permission) {
            $id = (int)(is_array($permission) ? ($permission['id'] ?? 0) : ($permission->id ?? 0));
            if ($id <= 0) {
                continue;
            }

            $map[$id] = [
                'id'        => $id,
                'parent_id' => (int)(is_array($permission) ? ($permission['parent_id'] ?? 0) : ($permission->parent_id ?? 0)),
                'type'      => (int)(is_array($permission) ? ($permission['type'] ?? 0) : ($permission->type ?? 0)),
            ];
        }

        return $map;
    }
}

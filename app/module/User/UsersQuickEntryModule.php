<?php

namespace app\module\User;

use app\dep\Permission\PermissionDep;
use app\dep\User\UsersQuickEntryDep;
use app\module\BaseModule;
use app\validate\User\UsersQuickEntryValidate;

/**
 * 用户快捷入口模块
 * 负责用户快捷入口的保存
 */
class UsersQuickEntryModule extends BaseModule
{
    private const MAX_QUICK_ENTRY_COUNT = 6;

    /**
     * 标准化并校验权限ID列表
     */
    private function normalizePermissionIds(array $permissionIds): array
    {
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
        self::throwIf(count($permissionIds) > self::MAX_QUICK_ENTRY_COUNT, '快捷入口最多保留6个');

        $permissionDep = $this->dep(PermissionDep::class);
        foreach ($permissionIds as $permissionId) {
            self::throwIf(!$permissionDep->get($permissionId), '权限不存在');
        }

        return $permissionIds;
    }

    /**
     * 原子保存快捷入口
     */
    public function save($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::save());
        $userId = (int)$request->userId;
        $permissionIds = $this->normalizePermissionIds($param['permission_ids']);

        return $this->withTransaction(function () use ($userId, $permissionIds) {
            $quickEntry = $this->dep(UsersQuickEntryDep::class)->replaceByUserId($userId, $permissionIds);
            return self::success(['quick_entry' => $quickEntry]);
        });
    }
}

<?php

namespace app\dep\User;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\User\UsersQuickEntryModel;
use support\Model;

/**
 * 用户快捷入口 Dep
 */
class UsersQuickEntryDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UsersQuickEntryModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 获取用户的快捷入口列表（按 sort 升序）
     */
    public function listByUserId(int $userId): array
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('permission_id', '>', 0)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 检查用户是否已添加某个权限
     */
    public function existsByUserAndPermission(int $userId, int $permissionId): bool
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('permission_id', $permissionId)
            ->where('is_del', CommonEnum::NO)
            ->exists();
    }

    /**
     * 获取用户当前最大 sort 值
     */
    public function getMaxSort(int $userId): int
    {
        return (int)$this->model
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->max('sort');
    }

    // ==================== 写入方法 ====================

    /**
     * 批量更新排序
     */
    public function updateSort(array $items): void
    {
        foreach ($items as $item) {
            $this->model
                ->where('id', $item['id'])
                ->update(['sort' => $item['sort']]);
        }
    }
}

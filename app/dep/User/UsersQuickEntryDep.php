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

    // ==================== 写入方法 ====================

    /**
     * 用最终顺序覆盖用户快捷入口
     */
    public function replaceByUserId(int $userId, array $permissionIds): array
    {
        $this->model
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);

        foreach ($permissionIds as $index => $permissionId) {
            $this->add([
                'user_id'       => $userId,
                'permission_id' => (int)$permissionId,
                'sort'          => $index + 1,
                'is_del'        => CommonEnum::NO,
            ]);
        }

        return $this->listByUserId($userId);
    }
}

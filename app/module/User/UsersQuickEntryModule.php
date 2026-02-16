<?php

namespace app\module\User;

use app\dep\User\UsersQuickEntryDep;
use app\module\BaseModule;
use app\validate\User\UsersQuickEntryValidate;

/**
 * 用户快捷入口模块
 * 负责：快捷入口的添加、删除、排序
 */
class UsersQuickEntryModule extends BaseModule
{
    /**
     * 添加快捷入口
     */
    public function add($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::add());

        $userId = $request->userId;
        $permissionId = (int)$param['permission_id'];

        $dep = $this->dep(UsersQuickEntryDep::class);

        // 检查是否已添加
        self::throwIf($dep->existsByUserAndPermission($userId, $permissionId), '该入口已添加');

        // 获取当前最大 sort，添加记录
        $maxSort = $dep->getMaxSort($userId);
        $id = $dep->add([
            'user_id'       => $userId,
            'permission_id' => $permissionId,
            'sort'          => $maxSort + 1,
        ]);

        return self::success(['id' => $id]);
    }

    /**
     * 删除快捷入口
     */
    public function del($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::del());
        $this->dep(UsersQuickEntryDep::class)->delete($param['id']);
        return self::success();
    }

    /**
     * 更新排序
     */
    public function sort($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::sort());
        $this->dep(UsersQuickEntryDep::class)->updateSort($param['items']);
        return self::success();
    }
}

<?php

namespace app\module\User;

use app\dep\User\UsersQuickEntryDep;
use app\module\BaseModule;
use app\validate\User\UsersQuickEntryValidate;

/**
 * 用户快捷入口模块
 */
class UsersQuickEntryModule extends BaseModule
{
    protected UsersQuickEntryDep $usersQuickEntryDep;

    public function __construct()
    {
        $this->usersQuickEntryDep = $this->dep(UsersQuickEntryDep::class);
    }

    /**
     * 添加快捷入口
     */
    public function add($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::add());
        
        $userId = $request->userId;
        $permissionId = (int)$param['permission_id'];

        // 检查是否已添加
        $exists = $this->usersQuickEntryDep->existsByUserAndPermission($userId, $permissionId);
        self::throwIf($exists, '该入口已添加');

        // 获取当前最大 sort
        $maxSort = $this->usersQuickEntryDep->getMaxSort($userId);

        // 添加
        $id = $this->usersQuickEntryDep->add([
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'sort' => $maxSort + 1,
        ]);

        return self::success(['id' => $id]);
    }

    /**
     * 删除快捷入口
     */
    public function del($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::del());
        
        $this->usersQuickEntryDep->delete($param['id']);

        return self::success();
    }

    /**
     * 更新排序
     */
    public function sort($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::sort());
        
        $this->usersQuickEntryDep->updateSort($param['items']);

        return self::success();
    }
}

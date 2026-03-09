<?php

namespace app\module\User;

use app\dep\Permission\PermissionDep;
use app\dep\User\UsersQuickEntryDep;
use app\module\BaseModule;
use app\validate\User\UsersQuickEntryValidate;

/**
 * ????????
 * ????????????????
 */
class UsersQuickEntryModule extends BaseModule
{
    /**
     * ??????
     */
    public function add($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::add());

        $userId = $request->userId;
        $permissionId = (int)$param['permission_id'];

        self::throwIf(!$this->dep(PermissionDep::class)->get($permissionId), '?????');

        $dep = $this->dep(UsersQuickEntryDep::class);
        self::throwIf($dep->existsByUserAndPermission($userId, $permissionId), '??????');

        $maxSort = $dep->getMaxSort($userId);
        $id = $dep->add([
            'user_id'       => $userId,
            'permission_id' => $permissionId,
            'sort'          => $maxSort + 1,
        ]);

        return self::success(['id' => $id]);
    }

    /**
     * ??????
     */
    public function del($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::del());
        $this->dep(UsersQuickEntryDep::class)->delete($param['id']);
        return self::success();
    }

    /**
     * ????
     */
    public function sort($request): array
    {
        $param = $this->validate($request, UsersQuickEntryValidate::sort());
        $this->dep(UsersQuickEntryDep::class)->updateSort($param['items']);
        return self::success();
    }
}

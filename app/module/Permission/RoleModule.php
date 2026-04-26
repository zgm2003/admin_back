<?php

namespace app\module\Permission;

use app\dep\Permission\RoleDep;
use app\dep\Permission\RolePermissionDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\service\Permission\AuthPlatformService;
use app\service\User\PermissionService;
use app\validate\Permission\RoleValidate;
use support\Cache;

/**
 * Role management module.
 * Responsible for role CRUD, default-role switching, and permission assignment cache invalidation.
 */
class RoleModule extends BaseModule
{
    /**
     * Initialize dictionaries for role permission assignment.
     */
    public function init($request)
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setPermissionTree()
            ->setPermissionPlatformArr()
            ->getDict();

        return self::success($data);
    }

    /**
     * Add role.
     */
    public function add($request)
    {
        $param = $this->validate($request, RoleValidate::add());
        $dep = $this->dep(RoleDep::class);
        self::throwIf($dep->existsByName($param['name']), '角色名已存在');

        $this->withTransaction(function () use ($param, $dep) {
            $roleId = $dep->add([
                'name' => $param['name'],
            ]);

            $this->dep(RolePermissionDep::class)->syncPermissions($roleId, $param['permission_id']);
        });

        return self::success();
    }

    /**
     * Delete role(s).
     */
    public function del($request)
    {
        $param = $this->validate($request, RoleValidate::del());

        $ids = is_array($param['id']) ? array_map('intval', $param['id']) : [(int)$param['id']];
        $dep = $this->dep(RoleDep::class);
        $roles = $dep->getMapActive($ids);

        self::throwIf($roles->isEmpty(), '角色不存在');
        self::throwIf($roles->count() !== count($ids), '包含不存在的角色');
        self::throwIf($dep->hasDefaultIn($ids), '默认角色不能删除');
        self::throwIf($this->dep(UsersDep::class)->getIdsByRoleIds($ids)->count() > 0, '角色已绑定用户，不能删除');

        $this->withTransaction(function () use ($dep, $ids) {
            $dep->delete($ids);
            $this->dep(RolePermissionDep::class)->deleteByRoleIds($ids);
        });

        $this->clearPermissionCacheByRoleIds($ids);

        return self::success();
    }

    /**
     * Edit role.
     */
    public function edit($request)
    {
        $param = $this->validate($request, RoleValidate::edit());

        $dep = $this->dep(RoleDep::class);
        self::throwIf($dep->existsByName($param['name'], $param['id']), '角色名已存在');

        $this->withTransaction(function () use ($param, $dep) {
            $dep->update($param['id'], [
                'name' => $param['name'],
            ]);

            $this->dep(RolePermissionDep::class)->syncPermissions((int)$param['id'], $param['permission_id']);
        });

        $this->clearPermissionCacheByRoleIds([(int)$param['id']]);

        return self::success();
    }

    /**
     * Role list, with permission_id always exposed as array.
     */
    public function list($request)
    {
        $param = $this->validate($request, RoleValidate::list());
        $resList = $this->dep(RoleDep::class)->list($param);
        $rolePermissionDep = $this->dep(RolePermissionDep::class);
        $permissionMap = $rolePermissionDep->getPermissionIdsByRoleIds($resList->pluck('id')->all());

        $data['list'] = $resList->map(fn($item) => [
            'id'            => $item['id'],
            'name'          => $item['name'],
            'permission_id' => $rolePermissionDep->filterToActiveAssignablePermissionIds($permissionMap[(int)$item['id']] ?? []),
            'is_default'    => $item['is_default'] ?? CommonEnum::NO,
            'created_at'    => $item['created_at'],
            'updated_at'    => $item['updated_at'],
        ]);
        $data['page'] = [
            'page_size'    => $resList->perPage(),
            'current_page' => $resList->currentPage(),
            'total_page'   => $resList->lastPage(),
            'total'        => $resList->total(),
        ];

        return self::paginate($data['list'], $data['page']);
    }

    /**
     * Set default role.
     */
    public function setDefault($request)
    {
        $param = $this->validate($request, RoleValidate::setDefault());

        $dep = $this->dep(RoleDep::class);
        $id = (int)$param['id'];
        $dep->getOrFail($id);

        try {
            $this->withTransaction(function () use ($dep, $id) {
                $dep->clearDefault();
                $dep->update($id, ['is_default' => CommonEnum::YES]);
            });
        } catch (\Throwable $e) {
            self::throw('设置默认角色失败');
        }
        return self::success();
    }

    /**
     * Clear permission cache for all users bound to the given roles.
     */
    private function clearPermissionCacheByRoleIds(array $roleIds): void
    {
        $userIds = $this->dep(UsersDep::class)->getIdsByRoleIds($roleIds);
        foreach ($userIds as $uid) {
            foreach (AuthPlatformService::getAllowedPlatforms() as $platform) {
                Cache::delete(PermissionService::buttonCacheKey((int)$uid, $platform));
            }
        }
    }
}

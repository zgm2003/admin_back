<?php

namespace app\module\Permission;

use app\dep\Permission\RoleDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\Permission\AuthPlatformService;
use app\validate\Permission\RoleValidate;
use support\Cache;

/**
 * 角色管理模块
 * 负责：角色 CRUD、设置默认角色、权限分配（修改角色后自动清理关联用户的权限缓存）
 */
class RoleModule extends BaseModule
{
    /**
     * 初始化（返回权限树字典，供角色分配权限时使用）
     */
    public function init($request)
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setPermissionTree()
            ->getDict();

        return self::success($data);
    }

    /**
     * 新增角色（角色名不可重复）
     */
    public function add($request)
    {
        $param = $this->validate($request, RoleValidate::add());
        self::throwIf($this->dep(RoleDep::class)->existsByName($param['name']), '角色名已存在');
        $this->assertPermissionPayloadFits($param['permission_id']);

        $this->dep(RoleDep::class)->add([
            'name'          => $param['name'],
            'permission_id' => json_encode($param['permission_id']),
        ]);

        return self::success();
    }

    /**
     * 删除角色（支持批量）
     * 校验：角色必须存在、不能删除默认角色
     * 删除后清理关联用户的权限缓存
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

        $dep->delete($ids);

        $this->clearPermissionCacheByRoleIds($ids);

        return self::success();
    }

    /**
     * 编辑角色（角色名排除自身的唯一校验）
     * 编辑后清理关联用户的权限缓存
     */
    public function edit($request)
    {
        $param = $this->validate($request, RoleValidate::edit());

        $dep = $this->dep(RoleDep::class);
        self::throwIf($dep->existsByName($param['name'], $param['id']), '角色名已存在');
        $this->assertPermissionPayloadFits($param['permission_id']);

        $dep->update($param['id'], [
            'name'          => $param['name'],
            'permission_id' => json_encode($param['permission_id']),
        ]);

        $this->clearPermissionCacheByRoleIds([(int)$param['id']]);

        return self::success();
    }

    /**
     * 角色列表（分页，permission_id JSON 解码后返回数组）
     */
    public function list($request)
    {
        $param = $this->validate($request, RoleValidate::list());
        $resList = $this->dep(RoleDep::class)->list($param);

        $data['list'] = $resList->map(fn($item) => [
            'id'            => $item['id'],
            'name'          => $item['name'],
            'permission_id' => json_decode($item['permission_id']),
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
     * 设置默认角色（唯一，事务内先清除再设置）
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

    // ==================== 私有方法 ====================

    /**
     * 校验 permission_id JSON 长度是否可落库
     */
    private function assertPermissionPayloadFits(array $permissionIds): void
    {
        $payload = json_encode(array_values($permissionIds));
        self::throwIf($payload === false, 'permission_id 编码失败');
        self::throwIf(strlen($payload) > 255, 'permission_id 长度超过 255，请减少权限节点数量');
    }

    /**
     * 清理指定角色关联的所有用户权限缓存
     * 遍历角色下所有用户 × 所有平台，逐一删除缓存 key
     */

    private function clearPermissionCacheByRoleIds(array $roleIds): void
    {
        $userIds = $this->dep(UsersDep::class)->getIdsByRoleIds($roleIds);
        foreach ($userIds as $uid) {
            foreach (AuthPlatformService::getAllowedPlatforms() as $platform) {
                Cache::delete("auth_perm_uid_{$uid}_{$platform}");
            }
        }
    }
}

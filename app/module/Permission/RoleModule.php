<?php

namespace app\module\Permission;

use app\dep\Permission\RoleDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Permission\RoleValidate;
use support\Cache;

class RoleModule extends BaseModule
{
    protected RoleDep $roleDep;

    public function __construct()
    {
        $this->roleDep = $this->dep(RoleDep::class);
    }

    public function init($request)
    {
        $dictService = $this->svc(DictService::class);
        $data['dict'] = $dictService
            ->setPermissionTree()
            ->getDict();

        return self::success($data);
    }

    public function add($request)
    {
        $param = $this->validate($request, RoleValidate::add());
        $resDep = $this->roleDep->findByName($param['name']);
        self::throwIf($resDep, '角色名已存在');
        $data = [
            'name' => $param['name'],
            'permission_id' => json_encode($param['permission_id']),
        ];
        $this->roleDep->add($data);
        
        return self::success();
    }

    public function del($request)
    {
        $param = $request->all();
        self::throwIf(empty($param['id']), 'ID不能为空');
        
        $ids = is_array($param['id']) ? array_map('intval', $param['id']) : [(int)$param['id']];
        $dep = $this->roleDep;
        $roles = $dep->getMapActive($ids);
        
        self::throwIf($roles->isEmpty(), '角色不存在');
        self::throwIf($roles->count() !== count($ids), '包含不存在的角色');
        self::throwIf($dep->hasDefaultIn($ids), '默认角色不能删除');
        
        $dep->delete($ids);

        $this->clearPermissionCacheByRoleIds($ids);

        return self::success();
    }

    public function edit($request)
    {
        $param = $this->validate($request, RoleValidate::edit());
        $dep = $this->roleDep;
        $resDep = $dep->findByName($param['name']);
        self::throwIf($resDep && $resDep['id'] != $param['id'], '角色名已存在');
        $data = [
            'name' => $param['name'],
            'permission_id' => json_encode($param['permission_id']),
        ];
        $dep->update($param['id'], $data);

        $this->clearPermissionCacheByRoleIds([(int)$param['id']]);

        return self::success();
    }

    public function list($request)
    {
        $dep = $this->roleDep;
        $param = $request->all();

        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;
        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'permission_id' => json_decode($item['permission_id']),
                'is_default' => $item['is_default'] ?? CommonEnum::NO,
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString()
            ];
        });
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];

        return self::paginate($data['list'], $data['page']);
    }

    /**
     * 设置默认角色（唯一）
     */
    public function setDefault($request)
    {
        $param = $this->validate($request, RoleValidate::setDefault());

        $dep = $this->roleDep;
        $id = (int)$param['id'];
        $role = $dep->find($id);
        self::throwIf(!$role || (isset($role['is_del']) && (int)$role['is_del'] !== CommonEnum::NO), '角色不存在');
        
        try {
            $this->withTransaction(function () use ($id) {
                $this->roleDep->clearDefault();
                $this->roleDep->update($id, ['is_default' => CommonEnum::YES]);
            });
        } catch (\Throwable $e) {
            self::throw('设置默认角色失败');
        }
        return self::success();
    }

    private function clearPermissionCacheByRoleIds(array $roleIds): void
    {
        $usersDep = $this->dep(UsersDep::class);
        $userIds = $usersDep->getIdsByRoleIds($roleIds);
        foreach ($userIds as $uid) {
            foreach (PermissionEnum::ALLOWED_PLATFORMS as $platform) {
                Cache::delete('auth_perm_uid_' . $uid . '_' . $platform);
            }
        }
    }
}

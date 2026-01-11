<?php

namespace app\module\User;

use app\dep\User\RoleDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\User\RoleValidate;
use support\Cache;

class RoleModule extends BaseModule
{
    protected RoleDep $roleDep;

    public function __construct()
    {
        $this->roleDep = new RoleDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setPermissionTree()
            ->getDict();

        return self::success($data);
    }

    public function add($request)
    {
        try { $param = $this->validate($request, RoleValidate::add()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $resDep = $this->roleDep->findByName($param['name']);
        if ($resDep) {
            return self::error('角色名已存在');
        }
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
        if (empty($param['id'])) {
            return self::error('ID不能为空');
        }
        $ids = is_array($param['id']) ? array_map('intval', $param['id']) : [ (int)$param['id'] ];
        $dep = $this->roleDep;
        $roles = $dep->getMapActive($ids);
        if ($roles->isEmpty()) {
            return self::error('角色不存在');
        }
        if ($roles->count() !== count($ids)) {
            return self::error('包含不存在的角色');
        }
        if ($dep->hasDefaultIn($ids)) {
            return self::error('默认角色不能删除');
        }
        $dep->delete($ids);

        // Clear cache for users with these roles
        $usersDep = new UsersDep();
        $userIds = $usersDep->getIdsByRoleIds($ids);
        foreach ($userIds as $uid) {
            Cache::delete('auth_perm_uid_' . $uid);
        }

        return self::success();
    }


    public function edit($request)
    {
        try { $param = $this->validate($request, RoleValidate::edit()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $dep = $this->roleDep;
        $resDep = $dep->findByName($param['name']);
        if ($resDep && $resDep['id'] != $param['id']) {
            return self::error('角色名已存在');
        }
        $data = [
            'name' => $param['name'],
            'permission_id' => json_encode($param['permission_id']),
        ];
        $dep->update($param['id'], $data);

        // Clear cache for users with this role
        $usersDep = new UsersDep();
        $userIds = $usersDep->getIdsByRoleIds([$param['id']]);
        foreach ($userIds as $uid) {
            Cache::delete('auth_perm_uid_' . $uid);
        }

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
        try { $param = $this->validate($request, RoleValidate::setDefault()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }

        $dep = $this->roleDep;
        $id = (int)$param['id'];
        $role = $dep->find($id);
        if (!$role || (isset($role['is_del']) && (int)$role['is_del'] !== CommonEnum::NO)) {
            return self::error('角色不存在');
        }
        try {
            $this->withTransaction(function () use ($id) {
                $this->roleDep->clearDefault();
                $this->roleDep->update($id, ['is_default' => CommonEnum::YES]);
            });
        } catch (\Throwable $e) {
            return self::error('设置默认角色失败');
        }
        return self::success();
    }


}


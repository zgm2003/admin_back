<?php

namespace app\module\Permission;

use app\dep\Permission\PermissionDep;
use app\enum\PermissionEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Permission\PermissionValidate;
use support\Redis;

class PermissionModule extends BaseModule
{
    protected PermissionDep $permissionDep;
    protected DictService $dictService;

    public function __construct()
    {
        $this->permissionDep = $this->dep(PermissionDep::class);
        $this->dictService = $this->svc(DictService::class);
    }

    public function init($request)
    {
        $data['dict'] = $this->dictService
            ->setPermissionTree()
            ->setPermissionTypeArr()
            ->setPermissionPlatformArr()
            ->getDict();

        return self::success($data);
    }

    public function add($request)
    {
        $param = $this->validate($request, PermissionValidate::add());
        $platform = $param['platform'];
        $parentId = empty($param['parent_id']) ? -1 : (int)$param['parent_id'];
        
        // 校验 parent_id 同平台（禁止跨平台挂载）
        if ($parentId !== -1) {
            $parentPlatform = $this->permissionDep->getPlatformById($parentId);
            self::throwIf($parentPlatform !== $platform, '父节点与当前平台不一致');
        }
        
        if ($param['type'] == PermissionEnum::TYPE_DIR) {
            self::throwIf(empty($param['i18n_key']), 'i18n_key 不能为空');
            self::throwIf(empty($param['show_menu']), 'show_menu 不能为空');
            
            // 唯一性检查：platform + i18n_key
            $exist = $this->permissionDep->findByPlatformI18nKey($platform, $param['i18n_key']);
            self::throwIf($exist, '该平台下 i18n_key 已存在');
            
            $data = [
                'name' => $param['name'],
                'parent_id' => $parentId,
                'icon' => $param['icon'] ?? '',
                'type' => $param['type'],
                'platform' => $platform,
                'i18n_key' => $param['i18n_key'],
                'sort' => $param['sort'],
                'show_menu' => $param['show_menu'],
            ];
            $this->permissionDep->add($data);
            
        } elseif ($param['type'] == PermissionEnum::TYPE_PAGE) {
            foreach (['path', 'component', 'i18n_key', 'show_menu'] as $f) {
                self::throwIf(empty($param[$f]), "{$f} 不能为空");
            }
            
            // 唯一性检查：platform + path
            $existPath = $this->permissionDep->findByPlatformPath($platform, $param['path']);
            self::throwIf($existPath, '该平台下路由 path 已存在');
            
            // 唯一性检查：platform + i18n_key
            $existI18n = $this->permissionDep->findByPlatformI18nKey($platform, $param['i18n_key']);
            self::throwIf($existI18n, '该平台下 i18n_key 已存在');
            
            $data = [
                'name' => $param['name'],
                'parent_id' => $parentId,
                'path' => $param['path'],
                'component' => $param['component'],
                'type' => $param['type'],
                'platform' => $platform,
                'icon' => $param['icon'] ?? '',
                'i18n_key' => $param['i18n_key'],
                'sort' => $param['sort'],
                'show_menu' => $param['show_menu'],
            ];
            $this->permissionDep->add($data);
            
        } elseif ($param['type'] == PermissionEnum::TYPE_BUTTON) {
            // H5/APP 平台按钮无需父级
            if ($platform !== PermissionEnum::PLATFORM_APP) {
                self::throwIf(empty($param['parent_id']), 'parent_id 不能为空');
            }
            self::throwIf(empty($param['code']), 'code 不能为空');
            
            // 唯一性检查：platform + code
            $exist = $this->permissionDep->findByPlatformCode($platform, $param['code']);
            self::throwIf($exist, '该平台下权限标识已存在');
            
            $data = [
                'name' => $param['name'],
                'parent_id' => $parentId,
                'code' => $param['code'],
                'type' => $param['type'],
                'platform' => $platform,
                'sort' => $param['sort'],
            ];
            $this->permissionDep->add($data);
        }

        PermissionDep::clearCache();
        DictService::clearPermissionCache();
        
        return self::success();
    }

    public function edit($request)
    {
        $param = $this->validate($request, PermissionValidate::edit());
        $platform = $param['platform'];
        $parentId = empty($param['parent_id']) ? -1 : (int)$param['parent_id'];
        $id = $param['id'];
        
        // 校验 parent_id 同平台（禁止跨平台挂载）
        if ($parentId !== -1) {
            $parentPlatform = $this->permissionDep->getPlatformById($parentId);
            self::throwIf($parentPlatform !== $platform, '父节点与当前平台不一致');
        }
        
        if ($param['type'] == PermissionEnum::TYPE_DIR) {
            self::throwIf(empty($param['i18n_key']), 'i18n_key 不能为空');
            self::throwIf(empty($param['show_menu']), 'show_menu 不能为空');
            
            // 唯一性检查：platform + i18n_key（排除自己）
            $exist = $this->permissionDep->findByPlatformI18nKey($platform, $param['i18n_key'], $id);
            self::throwIf($exist, '该平台下 i18n_key 已存在');
            
            $data = [
                'name' => $param['name'],
                'parent_id' => $parentId,
                'icon' => $param['icon'] ?? '',
                'type' => $param['type'],
                'platform' => $platform,
                'i18n_key' => $param['i18n_key'],
                'sort' => $param['sort'],
                'show_menu' => $param['show_menu'],
            ];
            $this->permissionDep->update($id, $data);
            
        } elseif ($param['type'] == PermissionEnum::TYPE_PAGE) {
            foreach (['path', 'component', 'i18n_key', 'show_menu'] as $f) {
                self::throwIf(empty($param[$f]), "{$f} 不能为空");
            }
            
            // 唯一性检查：platform + path（排除自己）
            $existPath = $this->permissionDep->findByPlatformPath($platform, $param['path'], $id);
            self::throwIf($existPath, '该平台下路由 path 已存在');
            
            // 唯一性检查：platform + i18n_key（排除自己）
            $existI18n = $this->permissionDep->findByPlatformI18nKey($platform, $param['i18n_key'], $id);
            self::throwIf($existI18n, '该平台下 i18n_key 已存在');
            
            $data = [
                'name' => $param['name'],
                'parent_id' => $parentId,
                'path' => $param['path'],
                'component' => $param['component'],
                'type' => $param['type'],
                'platform' => $platform,
                'icon' => $param['icon'] ?? '',
                'i18n_key' => $param['i18n_key'],
                'sort' => $param['sort'],
                'show_menu' => $param['show_menu'],
            ];
            $this->permissionDep->update($id, $data);
            
        } elseif ($param['type'] == PermissionEnum::TYPE_BUTTON) {
            self::throwIf(empty($param['parent_id']), 'parent_id 不能为空');
            self::throwIf(empty($param['code']), 'code 不能为空');
            
            // 唯一性检查：platform + code（排除自己）
            $exist = $this->permissionDep->findByPlatformCode($platform, $param['code'], $id);
            self::throwIf($exist, '该平台下权限标识已存在');
            
            $data = [
                'name' => $param['name'],
                'parent_id' => $parentId,
                'code' => $param['code'],
                'type' => $param['type'],
                'platform' => $platform,
                'sort' => $param['sort'] ?? 1,
            ];
            $this->permissionDep->update($id, $data);
        }

        PermissionDep::clearCache();
        DictService::clearPermissionCache();
        
        return self::success();
    }

    public function del($request)
    {
        $param = $this->validate($request, PermissionValidate::del());
        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];
        $this->permissionDep->delete($ids);
        
        PermissionDep::clearCache();
        DictService::clearPermissionCache();
        
        return self::success();
    }

    public function batchEdit($request)
    {
        $param = $this->validate($request, PermissionValidate::batchEdit());
        $ids = is_array($param['ids']) ? $param['ids'] : [$param['ids']];

        if ($param['field'] == 'description') {
            $data = ['description' => $param['description']];
            $this->permissionDep->update($ids, $data);
        }

        return self::success();
    }

    public function list($request)
    {
        $param = $this->validate($request, PermissionValidate::list());
        $resList = $this->permissionDep->list($param);

        $data['list'] = $resList->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'path' => $item->path,
                'parent_id' => $item->parent_id,
                'icon' => $item->icon,
                'component' => $item->component,
                'status' => $item->status,
                'type' => $item->type,
                'type_name' => PermissionEnum::$typeArr[$item->type],
                'code' => $item->code,
                'i18n_key' => $item->i18n_key,
                'sort' => $item->sort,
                'show_menu' => $item->show_menu,
            ];
        });

        $data['menu_tree'] = listToTree($data['list']->toArray(), -1);

        return self::success($data['menu_tree']);
    }

    /**
     * APP/H5/小程序 按钮权限列表（扁平化）
     */
    public function appButtonList($request)
    {
        $param = $this->validate($request, PermissionValidate::list());
        $param['type'] = PermissionEnum::TYPE_BUTTON;
        
        $resList = $this->permissionDep->list($param);
        
        $data = $resList->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'status' => $item->status,
                'code' => $item->code,
                'sort' => $item->sort,
                'platform' => $item->platform,
                'platform_name' => PermissionEnum::$platformArr[$item->platform] ?? $item->platform,
            ];
        });
        
        return self::success($data->toArray());
    }

    /**
     * APP/H5/小程序 按钮权限新增
     */
    public function appButtonAdd($request)
    {
        $param = $this->validate($request, PermissionValidate::add());
        $platform = $param['platform'];
        
        // 只允许非 PC 后台平台
        self::throwIf($platform === PermissionEnum::PLATFORM_ADMIN, '请使用 PC 后台权限管理');
        self::throwIf(empty($param['code']), 'code 不能为空');
        
        // 唯一性检查：platform + code
        $exist = $this->permissionDep->findByPlatformCode($platform, $param['code']);
        self::throwIf($exist, '该平台下权限标识已存在');
        
        $data = [
            'name' => $param['name'],
            'parent_id' => -1,
            'code' => $param['code'],
            'type' => PermissionEnum::TYPE_BUTTON,
            'platform' => $platform,
            'sort' => $param['sort'] ?? 1,
        ];
        $this->permissionDep->add($data);
        
        PermissionDep::clearCache();
        DictService::clearPermissionCache();
        
        return self::success();
    }

    /**
     * APP/H5/小程序 按钮权限编辑
     */
    public function appButtonEdit($request)
    {
        $param = $this->validate($request, PermissionValidate::edit());
        $platform = $param['platform'];
        $id = $param['id'];
        
        // 只允许非 PC 后台平台
        self::throwIf($platform === PermissionEnum::PLATFORM_ADMIN, '请使用 PC 后台权限管理');
        self::throwIf(empty($param['code']), 'code 不能为空');
        
        // 唯一性检查：platform + code（排除自己）
        $exist = $this->permissionDep->findByPlatformCode($platform, $param['code'], $id);
        self::throwIf($exist, '该平台下权限标识已存在');
        
        $data = [
            'name' => $param['name'],
            'parent_id' => -1,
            'code' => $param['code'],
            'type' => PermissionEnum::TYPE_BUTTON,
            'platform' => $platform,
            'sort' => $param['sort'] ?? 1,
        ];
        $this->permissionDep->update($id, $data);
        
        PermissionDep::clearCache();
        DictService::clearPermissionCache();
        
        return self::success();
    }

    public function status($request)
    {
        $param = $this->validate($request, PermissionValidate::status());
        $this->permissionDep->update($param['id'], ['status' => $param['status']]);
        
        PermissionDep::clearCache();
        DictService::clearPermissionCache();
        self::clearAllUserPermCache();
        
        return self::success();
    }

    /**
     * 清除所有用户权限缓存 auth_perm_uid_*
     * 后台管理系统用户量有限，直接用 keys + del
     */
    public static function clearAllUserPermCache(): void
    {
        $redis = Redis::connection('cache');
        $keys = $redis->keys('cache:auth_perm_uid_*');
        if (!empty($keys)) {
            $redis->del(...$keys);
        }
    }
}

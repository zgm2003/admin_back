<?php

namespace app\module\Permission;

use app\dep\Permission\PermissionDep;
use app\enum\PermissionEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Permission\PermissionValidate;

class PermissionModule extends BaseModule
{
    protected PermissionDep $permissionDep;

    public function __construct()
    {
        $this->permissionDep = new PermissionDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setPermissionTree()
            ->setPermissionTypeArr()
            ->getDict();

        return self::success($data);
    }

    public function add($request)
    {
        $param = $this->validate($request, PermissionValidate::add());
        
        if ($param['type'] == PermissionEnum::TYPE_DIR) {
            self::throwIf(empty($param['i18n_key']), 'i18n_key 不能为空');
            self::throwIf(empty($param['show_menu']), 'show_menu 不能为空');
            $data = [
                'name' => $param['name'],
                'parent_id' => empty($param['parent_id']) ? -1 : $param['parent_id'],
                'icon' => $param['icon'],
                'type' => $param['type'],
                'i18n_key' => $param['i18n_key'],
                'sort' => $param['sort'],
                'show_menu' => $param['show_menu'],
            ];
            $this->permissionDep->add($data);
        } elseif ($param['type'] == PermissionEnum::TYPE_PAGE) {
            foreach (['path','component','i18n_key', 'show_menu'] as $f) {
                self::throwIf(empty($param[$f]), "{$f} 不能为空");
            }
            $data = [
                'name' => $param['name'],
                'parent_id' => empty($param['parent_id']) ? -1 : $param['parent_id'],
                'path' => $param['path'],
                'component' => $param['component'],
                'type' => $param['type'],
                'icon' => $param['icon'],
                'i18n_key' => $param['i18n_key'],
                'sort' => $param['sort'],
                'show_menu' => $param['show_menu'],
            ];
            $this->permissionDep->add($data);
        } elseif ($param['type'] == PermissionEnum::TYPE_BUTTON) {
            foreach (['parent_id','code'] as $f) {
                self::throwIf(empty($param[$f]), "{$f} 不能为空");
            }
            $data = [
                'name' => $param['name'],
                'parent_id' => $param['parent_id'],
                'code' => $param['code'],
                'type' => $param['type'],
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
        
        if ($param['type'] == PermissionEnum::TYPE_DIR) {
            self::throwIf(empty($param['i18n_key']), 'i18n_key 不能为空');
            self::throwIf(empty($param['show_menu']), 'show_menu 不能为空');
            $data = [
                'name' => $param['name'],
                'parent_id' => empty($param['parent_id']) ? -1 : $param['parent_id'],
                'icon' => $param['icon'],
                'type' => $param['type'],
                'i18n_key' => $param['i18n_key'],
                'sort' => $param['sort'],
                'show_menu' => $param['show_menu'],
            ];
            $this->permissionDep->update($param['id'], $data);
        } elseif ($param['type'] == PermissionEnum::TYPE_PAGE) {
            foreach (['path','component','i18n_key', 'show_menu'] as $f) {
                self::throwIf(empty($param[$f]), "{$f} 不能为空");
            }
            $data = [
                'name' => $param['name'],
                'parent_id' => empty($param['parent_id']) ? -1 : $param['parent_id'],
                'path' => $param['path'],
                'component' => $param['component'],
                'type' => $param['type'],
                'icon' => $param['icon'],
                'i18n_key' => $param['i18n_key'],
                'sort' => $param['sort'],
                'show_menu' => $param['show_menu'],
            ];
            $this->permissionDep->update($param['id'], $data);
        } elseif ($param['type'] == PermissionEnum::TYPE_BUTTON) {
            foreach (['parent_id','code'] as $f) {
                self::throwIf(empty($param[$f]), "{$f} 不能为空");
            }
            $data = [
                'name' => $param['name'],
                'parent_id' => $param['parent_id'],
                'code' => $param['code'],
                'type' => $param['type'],
            ];
            $this->permissionDep->update($param['id'], $data);
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
        $param = $request->all();
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

    public function status($request)
    {
        $param = $this->validate($request, PermissionValidate::status());
        $this->permissionDep->update($param['id'], ['status' => $param['status']]);
        
        PermissionDep::clearCache();
        DictService::clearPermissionCache();
        
        return self::success();
    }
}

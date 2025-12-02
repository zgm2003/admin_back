<?php

namespace app\module\User;

use app\dep\User\PermissionDep;
use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\module\BaseModule;
use app\service\DictService;

class PermissionModule extends BaseModule
{
    public $PermissionDep;

    public function __construct()
    {
        $this->PermissionDep = new PermissionDep();
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
        $param = $request->all();

        foreach (['name', 'type'] as $f) {
            if (empty($param[$f])) {
                return self::error("{$f} 不能为空");
            }
        }
        if ($param['type'] == PermissionEnum::TYPE_DIR) {
            if (empty($param['i18n_key'])) {
                return self::error('i18n_key 不能为空');
            }
            if (empty($param['parent_id'])) {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => -1,
                    'icon' => $param['icon'],
                    'type' => $param['type'],
                    'i18n_key' => $param['i18n_key'],
                ];
                $this->PermissionDep->add($data);
            } else {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => $param['parent_id'],
                    'icon' => $param['icon'],
                    'type' => $param['type'],
                    'i18n_key' => $param['i18n_key'],
                ];
                $this->PermissionDep->add($data);
            }
        } elseif ($param['type'] == PermissionEnum::TYPE_PAGE) {
            foreach (['path','component'] as $f) {
                if (empty($param[$f])) {
                    return self::error("{$f} 不能为空");
                }
            }
            if (empty($param['i18n_key'])) {
                return self::error('i18n_key 不能为空');
            }
            // 判断是否是顶级菜单
            if (empty($param['parent_id'])) {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => -1,
                    'path' => $param['path'],
                    'component' => $param['component'],
                    'type' => $param['type'],
                    'i18n_key' => $param['i18n_key'],
                ];
                $this->PermissionDep->add($data);
            } else {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => $param['parent_id'],
                    'path' => $param['path'],
                    'component' => $param['component'],
                    'type' => $param['type'],
                    'i18n_key' => $param['i18n_key'],
                ];
                $this->PermissionDep->add($data);
            }
        } elseif ($param['type'] == PermissionEnum::TYPE_BUTTON) {
            foreach (['parent_id','code'] as $f) {
                if (empty($param[$f])) {
                    return self::error("{$f} 不能为空");
                }
            }
            $data = [
                'name' => $param['name'],
                'parent_id' => $param['parent_id'],
                'code' => $param['code'],
                'type' => $param['type'],
            ];
            $this->PermissionDep->add($data);
        }

        return self::success();
    }

    public function edit($request)
    {
        $param = $request->all();

        foreach (['name', 'type','id'] as $f) {
            if (empty($param[$f])) {
                return self::error("{$f} 不能为空");
            }
        }
        if ($param['type'] == PermissionEnum::TYPE_DIR) {
            if (empty($param['i18n_key'])) {
                return self::error('i18n_key 不能为空');
            }
            if (empty($param['parent_id'])) {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => -1,
                    'icon' => $param['icon'],
                    'type' => $param['type'],
                    'i18n_key' => $param['i18n_key'],
                ];
                $this->PermissionDep->edit($param['id'],$data);
            } else {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => $param['parent_id'],
                    'icon' => $param['icon'],
                    'type' => $param['type'],
                    'i18n_key' => $param['i18n_key'],
                ];
                $this->PermissionDep->edit($param['id'],$data);
            }
        } elseif ($param['type'] == PermissionEnum::TYPE_PAGE) {
            foreach (['path','component'] as $f) {
                if (empty($param[$f])) {
                    return self::error("{$f} 不能为空");
                }
            }
            if (empty($param['i18n_key'])) {
                return self::error('i18n_key 不能为空');
            }
            // 判断是否是顶级菜单
            if (empty($param['parent_id'])) {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => -1,
                    'path' => $param['path'],
                    'component' => $param['component'],
                    'type' => $param['type'],
                    'i18n_key' => $param['i18n_key'],
                ];
                $this->PermissionDep->edit($param['id'],$data);
            } else {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => $param['parent_id'],
                    'path' => $param['path'],
                    'component' => $param['component'],
                    'type' => $param['type'],
                    'i18n_key' => $param['i18n_key'],
                ];
                $this->PermissionDep->edit($param['id'],$data);
            }
        } elseif ($param['type'] == PermissionEnum::TYPE_BUTTON) {
            foreach (['parent_id','code'] as $f) {
                if (empty($param[$f])) {
                    return self::error("{$f} 不能为空");
                }
            }
            $data = [
                'name' => $param['name'],
                'parent_id' => $param['parent_id'],
                'code' => $param['code'],
                'type' => $param['type'],
            ];
            $this->PermissionDep->edit($param['id'],$data);
        }

        return self::success();
    }

    public function del($request)
    {
        $param = $request->all();
        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];
        $dep = $this->PermissionDep;

        $dep->del($ids, ['is_del' => CommonEnum::YES]);
        return self::success();
    }

    public function batchEdit($request)
    {

        $param = $request->all();
        $ids = is_array($param['ids']) ? $param['ids'] : [$param['ids']];
        $dep = $this->PermissionDep;

        if ($param['field'] == 'description') {
            $data = [
                'description' => $param['description'],
            ];
            $dep->batchEdit($ids, $data);
        }


        return self::success();

    }

    public function list($request)
    {
        $param = $request->all();

        $param['page_size'] = 200;
        $param['current_page'] = 1;

        $PermissionDep = $this->PermissionDep;
        $resList = $PermissionDep->list($param);

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
            ];
        });

        $data['menu_tree'] = listToTree($data['list']->toArray(), -1);

        return self::success($data['menu_tree']);
    }

    public function status($request)
    {
        $param = $request->all();
        $data = [
            'status' => $param['status'],
        ];
        $this->PermissionDep->edit($param['id'], $data);
        return self::success();
    }


}


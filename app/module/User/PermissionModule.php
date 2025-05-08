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


        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();

       if ($param['type'] ==  PermissionEnum::TYPE_MENU){
           if (empty($param['name'])
           ) {
               return self::response([], '缺少必填参数', 100);
           }
           if (($param['path'] && empty($param['component'])) || (empty($param['path']) && $param['component'])) {
               return self::response([], 'path和component必须同时填写', 100);
           }

           // 判断是否是顶级菜单
           if (empty($param['parent_id'])) {
               $data = [
                   'name' => $param['name'],
                   'parent_id' => '-1',
                   'path' => $param['path'],
                   'icon' => $param['icon'],
                   'type' => $param['type'],
               ];
               $this->PermissionDep->add($data);
           } else {
               $data = [
                   'name' => $param['name'],
                   'parent_id' => $param['parent_id'],
                   'path' => $param['path'],
                   'icon' => $param['icon'],
                   'component' => $param['component'],
                   'type' => $param['type'],
               ];
               $this->PermissionDep->add($data);
           }
       }
       if ($param['type'] ==  PermissionEnum::TYPE_BUTTON){
           foreach (['name','code'] as $f) {
               if (empty($param[$f])) {
                   return self::response([], "{$f} 不能为空", 100);
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

        return self::response();
    }

    public function edit($request)
    {
        $param = $request->all();
        if ($param['type'] ==  PermissionEnum::TYPE_MENU){
            if (empty($param['name'])
            ) {
                return self::response([], '缺少必填参数', 100);
            }
            if (($param['path'] && empty($param['component'])) || (empty($param['path']) && $param['component'])) {
                return self::response([], 'path和component必须同时填写', 100);
            }

            // 判断是否是顶级菜单
            if (empty($param['parent_id'])) {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => '-1',
                    'path' => $param['path'],
                    'icon' => $param['icon'],
                    'type' => $param['type'],
                ];
                $this->PermissionDep->edit($param['id'],$data);
            } else {
                $data = [
                    'name' => $param['name'],
                    'parent_id' => $param['parent_id'],
                    'path' => $param['path'],
                    'icon' => $param['icon'],
                    'component' => $param['component'],
                    'type' => $param['type'],
                ];
                $this->PermissionDep->edit($param['id'],$data);
            }
        }
        if ($param['type'] ==  PermissionEnum::TYPE_BUTTON){
            foreach (['name','code',] as $f) {
                if (empty($param[$f])) {
                    return self::response([], "{$f} 不能为空", 100);
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

        return self::response();
    }

    public function del($request)
    {
        $param = $request->all();
        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];
        $dep = $this->PermissionDep;

        $dep->del($ids, ['is_del' => CommonEnum::YES]);
        return self::response();
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


        return self::response();

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
            ];
        });

        $data['menu_tree'] = listToTree($data['list']->toArray(), -1);

        return self::response($data['menu_tree']);
    }
    public function status($request)
    {
        $param = $request->all();
        $data = [
            'status' => $param['status'],
        ];
        $this->PermissionDep->edit($param['id'],$data);
        return self::response();
    }


}


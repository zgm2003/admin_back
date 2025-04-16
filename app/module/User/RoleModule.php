<?php

namespace app\module\User;

use app\dep\User\RoleDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;


class RoleModule extends BaseModule
{
    public $roleDep;

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

        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();
        if (empty($param['name'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $resDep = $this->roleDep->firstByName($param['name']);
        if ($resDep){
            return self::response([], '角色名已存在', 100);
        }
        $data = [
            'name' => $param['name'],
            'permission_id' => json_encode($param['permission_id']),
        ];
        $this->roleDep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->roleDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();

        $dep = $this->roleDep;
        if (empty($param['name'])

        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $resDep = $dep->firstByName($param['name']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::response([], '角色名已存在', 100);
        }
        $data = [
            'name' => $param['name'],
            'permission_id' => json_encode($param['permission_id']),
        ];
        $dep->edit($param['id'], $data);

        return self::response();
    }

    public function list($request)
    {

        $dep = $this->roleDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 50;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'permission_id' => json_decode($item['permission_id']),
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

        return self::response($data);
    }

}


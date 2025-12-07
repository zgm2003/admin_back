<?php

namespace app\module\User;

use app\dep\User\RoleDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;


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

        return self::success($data);
    }

    public function add($request)
    {
        try {
            $param = v::input($request->all(), [
                'name'          => v::length(1, 64)->setName('角色名'),
                'permission_id' => v::arrayType()->setName('权限')
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $resDep = $this->roleDep->firstByName($param['name']);
        if ($resDep){
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

        $dep = $this->roleDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::success();
    }

    public function edit($request)
    {
        try {
            $param = v::input($request->all(), [
                'id'            => v::intVal()->setName('ID'),
                'name'          => v::length(1, 64)->setName('角色名'),
                'permission_id' => v::arrayType()->setName('权限')
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $dep = $this->roleDep;
        $resDep = $dep->firstByName($param['name']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::error('角色名已存在');
        }
        $data = [
            'name' => $param['name'],
            'permission_id' => json_encode($param['permission_id']),
        ];
        $dep->edit($param['id'], $data);

        return self::success();
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

        return self::paginate($data['list'], $data['page']);
    }

}


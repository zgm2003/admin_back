<?php

namespace app\module\Chat;

use app\dep\Chat\RoomDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use Carbon\Carbon;


class RoomModule extends BaseModule
{
    public $roomDep;

    public function __construct()
    {
        $this->roomDep = new RoomDep();

    }
    public function init($request)
    {
        $dictService = new DictService();
        $user = $request->user();
        $data['dict'] = $dictService
            ->setUserRoomArr($user->id)
            ->setUserNotRoomArr($user->id)
            ->getDict();
        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();
        foreach (['name','icon'] as $f) {
            if (empty($param[$f])) {
                return self::response([], "{$f} 不能为空", 100);
            }
        }
        $resDep = $this->roomDep->firstByName($param['name']);
        if ($resDep){
            return self::response([], '该房间已存在', 100);
        }
        $data = [
            'name' => $param['name'],
            'icon' => $param['icon'],
            'is_enable' => $param['is_enable'],
            'is_check' => $param['is_check'],
            'is_lock' => $param['is_lock'],
            'password' => $param['is_lock'] == CommonEnum::YES ? $param['password'] : '',
        ];
        $this->roomDep->add($data);
        return self::response();
    }

    public function edit($request)
    {
        $param = $request->all();
        foreach (['name','icon'] as $f) {
            if (empty($param[$f])) {
                return self::response([], "{$f} 不能为空", 100);
            }
        }
        $resDep = $this->roomDep->firstByName($param['name']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::response([], '该房间已存在', 100);
        }
        $data = [
            'name' => $param['name'],
            'icon' => $param['icon'],
            'is_enable' => $param['is_enable'],
            'is_check' => $param['is_check'],
            'is_lock' => $param['is_lock'],
            'password' => $param['is_lock'] == CommonEnum::YES ? $param['password'] : '',
        ];
        $this->roomDep->edit($param['id'],$data);
        return self::response();
    }
    public function del($request)
    {

        $param = $request->all();

        $dep = $this->roomDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }


    public function list($request)
    {
        $dep = $this->roomDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 10;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'icon' => $item['icon'],
                'is_enable' => $item['is_enable'],
                'is_check' => $item['is_check'],
                'is_lock' => $item['is_lock'],
                'password' => $item['password'],
                'is_del' => $item['is_del'],
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
    public function isEnable($request)
    {
        $param = $request->all();
        $data = [
            'is_enable' => $param['is_enable'],
        ];
        $this->roomDep->edit($param['id'],$data);
        return self::response();
    }


}


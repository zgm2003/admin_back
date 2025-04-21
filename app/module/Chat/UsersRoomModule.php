<?php

namespace app\module\Chat;

use app\dep\Chat\RoomDep;
use app\dep\Chat\UsersRoomDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use Carbon\Carbon;


class UsersRoomModule extends BaseModule
{
    public $UserRoomDep;
    public $roomDep;
    public $userDep;

    public function __construct()
    {
        $this->UserRoomDep = new UsersRoomDep();
        $this->roomDep = new RoomDep();
        $this->userDep = new UsersDep();
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
        $user = $request->user();
        if (
            empty($param['room_id'])
        ) {
            return self::response([], '房间不能为空', 100);
        }
        $resRoom = $this->roomDep->first($param['room_id']);
        if ($resRoom->is_lock == CommonEnum::YES && $resRoom->password != $param['password']){
            return self::response([], '密码错误', 100);
        }
        $data = [
            'user_id' => $user->id,
            'room_id' => $param['room_id'],
        ];
        $this->UserRoomDep->add($data);
        return self::response();
    }

    public function edit($request)
    {
        $param = $request->all();
        if (
            empty($param['name']) || empty($param['icon'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $resDep = $this->UserRoomDep->firstByName($param['name']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::response([], '该房间已存在', 100);
        }
        $data = [
            'name' => $param['name'],
            'icon' => $param['icon'],
        ];
        $this->UserRoomDep->edit($param['id'],$data);
        return self::response();
    }
    public function del($request)
    {

        $param = $request->all();

        $dep = $this->UserRoomDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }


    public function list($request)
    {
        $dep = $this->UserRoomDep;
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
        $this->UserRoomDep->edit($param['id'],$data);
        return self::response();
    }


}


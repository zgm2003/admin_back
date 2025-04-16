<?php

namespace app\module\Web;

use app\dep\Web\MessageDep;
use app\dep\User\UsersDep;
use app\dep\User\UsersTokenDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;


class MessageModule extends BaseModule
{
    public $messageDep;
    public $userDep;
    public $tokenDep;

    public function __construct()
    {
        $this->messageDep = new MessageDep();
        $this->userDep = new UsersDep();
        $this->tokenDep = new UsersTokenDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->getDict();

        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();
        if (empty($param['content'])
        ) {
            return self::response([], '留言不能为空', 100);
        }
        $userId = $this->tokenDep->firstByToken($param['token'])->user_id ?? 0;
//        $userId = $this->userDep->firstByToken($param['token'])->id ?? 0;

        $data = [
            'user_id' => $userId,
            'content' => $param['content'],
        ];
        $this->messageDep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->messageDep;

        $dep->del($param['id']);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();

        $dep = $this->messageDep;
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

        $dep = $this->messageDep;
        $userDep = $this->userDep;
        $param = $request->all();


        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 100;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) use($userDep) {
            $resUser = $userDep->first($item['user_id']);
            return [
                'id' => $item['id'],
                'username' => $resUser['username']??'游客',
                'avatar' => $resUser['avatar']??env('VISITOR_AVATAR_URL'),
                'content' => $item['content'],
//                'created_at' => $item['created_at']->toDateTimeString(),
//                'updated_at' => $item['updated_at']->toDateTimeString()
            ];
        });

        return self::response($data);
    }

}


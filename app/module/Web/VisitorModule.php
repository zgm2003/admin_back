<?php

namespace app\module\Web;

use app\dep\User\UsersDep;
use app\dep\Web\VisitorDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use Carbon\Carbon;


class VisitorModule extends BaseModule
{
    public $visitorDep;
    public $userDep;

    public function __construct()
    {
        $this->visitorDep = new VisitorDep();
        $this->userDep = new UsersDep();
    }
    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->getDict();
        return self::response($data);
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->visitorDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }


    public function list($request)
    {
        $dep = $this->visitorDep;
        $userDep = $this->userDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 50;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);


        $data['list'] = $resList->map(function ($item) use($userDep) {
           $resUser = $userDep->first($item['user_id']);

            return [
                'id' => $item['id'],
                'username' => $resUser ? $resUser['username'] : '游客',
                'avatar' => $resUser ? $resUser['avatar'] : env('VISITOR_AVATAR_URL'),
                'ip' => $item['ip'],
                'city' => $item['city'],
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


}


<?php

namespace app\module\System;

use app\dep\User\UsersDep;
use app\dep\User\UsersLoginLogDep;
use app\module\BaseModule;
use app\service\DictService;

class UsersLoginLogModule extends BaseModule
{
    public $usersLoginLogDep;
    public $userDep;

    public function __construct()
    {
        $this->usersLoginLogDep = new UsersLoginLogDep();
        $this->userDep = new UsersDep();
    }

    public function init()
    {
        $dictService = new DictService();

        $dict = $dictService
            ->setUserArr()
            ->getDict();

        $data['dict'] = $dict;

        return self::success($data);
    }

    public function list($request)
    {
        $param = $request->all();
        $dep = $this->usersLoginLogDep;
        $userDep = $this->userDep;
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) use ($userDep) {
            // 如果日志中有 user_id，尝试获取用户名；否则使用日志中的 email
            $username = 'Unknown';
            if (!empty($item['user_id'])) {
                $resUser = $userDep->first($item['user_id']);
                if ($resUser) {
                    $username = $resUser['username'];
                }
            }
            
            return [
                'id' => $item['id'],
                'user_name' => $username,
                'email' => $item['email'],
                'platform' => $item['platform'],
                'ip' => $item['ip'],
                'ua' => $item['ua'],
                'success' => $item['success'],
                'reason' => $item['reason'] ?? '',
                'created_at' => $item['created_at']->toDateTimeString(),
            ];
        });
        
        $data['page'] = [
            'page_size' => (int)$param['page_size'],
            'current_page' => (int)$param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];

        return self::paginate($data['list'], $data['page']);
    }
}

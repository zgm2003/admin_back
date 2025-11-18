<?php

namespace app\module\System;

//导入部分
use app\dep\System\OperationLogDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;

class OperationLogModule extends BaseModule
{
    public $operationLogDep;
    public $userDep;

    public function __construct()
    {
        $this->operationLogDep = new OperationLogDep();
        $this->userDep = new UsersDep();
    }


    public function init(){

        $dictService = new DictService();

        $dict = $dictService
            ->setUserArr()
            ->getDict();

        $data['dict'] = $dict;

        return self::success($data);

    }


    public function del($request)
    {

        $param = $request->all();

        $dep = $this->operationLogDep;

        $dep->del($param['id']);

        return self::success();
    }
    public function list($request)
    {

        $dep = $this->operationLogDep;
        $userDep = $this->userDep;
        $param = $request->all();
        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 50;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) use ($userDep){
            $resUser = $userDep->first($item['user_id']);
            return [
                'id' => $item['id'],
                'user_name' => $resUser['username'],
                'user_email' => $resUser['email'],
                'action' => $item['action'],
                'request_data' => $item['request_data'],
                'response_data' => $item['response_data'],
                'is_success' => $item['is_success'],
                'created_at' => $item['created_at']->toDateTimeString(),

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


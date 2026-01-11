<?php

namespace app\module\System;

//导入部分
use app\dep\System\OperationLogDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\System\OperationLogValidate;

class OperationLogModule extends BaseModule
{
    protected OperationLogDep $operationLogDep;
    protected UsersDep $usersDep;

    public function __construct()
    {
        $this->operationLogDep = new OperationLogDep();
        $this->usersDep = new UsersDep();
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
        try { $param = $this->validate($request, OperationLogValidate::del()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }

        $this->operationLogDep->delete($param['id']);

        return self::success();
    }
    public function list($request)
    {
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

        $resList = $this->operationLogDep->list($param);
        
        // === 优化：批量预加载用户数据 ===
        $userIds = $resList->pluck('user_id')->unique()->toArray();
        $userMap = $this->usersDep->getMap($userIds);

        $data['list'] = $resList->map(function ($item) use ($userMap){
            $resUser = $userMap->get($item['user_id']);
            return [
                'id' => $item['id'],
                'user_name' => $resUser->username ?? 'Unknown',
                'user_email' => $resUser->email ?? '',
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


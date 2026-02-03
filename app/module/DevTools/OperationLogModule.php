<?php

namespace app\module\DevTools;

use app\dep\DevTools\OperationLogDep;
use app\dep\User\UsersDep;
use app\module\BaseModule;
use app\validate\DevTools\OperationLogValidate;

class OperationLogModule extends BaseModule
{
    protected OperationLogDep $operationLogDep;
    protected UsersDep $usersDep;

    public function __construct()
    {
        $this->operationLogDep = new OperationLogDep();
        $this->usersDep = new UsersDep();
    }

    // init 无需返回用户列表，前端使用远程搜索
    public function init($request)
    {
        return self::success();
    }

    public function del($request)
    {
        $param = $this->validate($request, OperationLogValidate::del());

        $this->operationLogDep->delete($param['id']);

        return self::success();
    }

    public function list($request)
    {
        $param = $this->validate($request, OperationLogValidate::list());
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
            'page_size' => $resList->perPage(),
            'current_page' => $resList->currentPage(),
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];

        return self::paginate($data['list'], $data['page']);
    }

    /**
     * 游标分页列表（深分页优化）
     */
    public function listCursor($request)
    {
        $param = $this->validate($request, OperationLogValidate::listCursor());

        $result = $this->operationLogDep->listByCursor($param);
        
        // 批量预加载用户数据
        $userIds = $result['list']->pluck('user_id')->unique()->toArray();
        $userMap = $this->usersDep->getMap($userIds);

        $list = $result['list']->map(function ($item) use ($userMap) {
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

        return self::cursorPaginate($list, $result['next_cursor'], $result['has_more']);
    }
}

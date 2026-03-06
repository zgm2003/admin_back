<?php

namespace app\module\System;

use app\dep\System\OperationLogDep;
use app\dep\User\UsersDep;
use app\module\BaseModule;
use app\validate\System\OperationLogValidate;

/**
 * 操作日志模块
 * 负责：后台操作日志的列表查询（普通分页 + 游标分页）、删除
 * 列表返回时批量预加载用户信息，避免 N+1 查询
 */
class OperationLogModule extends BaseModule
{
    /**
     * 初始化（当前无需返回字典，前端用户搜索走远程接口）
     */
    public function init($request)
    {
        return self::success();
    }

    /**
     * 删除操作日志（支持批量）
     */
    public function del($request)
    {
        $param = $this->validate($request, OperationLogValidate::del());
        $this->dep(OperationLogDep::class)->delete($param['id']);

        return self::success();
    }

    /**
     * 操作日志列表（普通分页，批量预加载用户数据）
     */
    public function list($request)
    {
        $param = $this->validate($request, OperationLogValidate::list());
        $resList = $this->dep(OperationLogDep::class)->list($param);

        // 批量预加载用户数据，避免 N+1
        $userIds = $resList->pluck('user_id')->unique()->toArray();
        $userMap = $this->dep(UsersDep::class)->getMap($userIds);

        $data['list'] = $resList->map(fn($item) => $this->buildLogItem($item, $userMap));

        $data['page'] = [
            'page_size'    => $resList->perPage(),
            'current_page' => $resList->currentPage(),
            'total_page'   => $resList->lastPage(),
            'total'        => $resList->total(),
        ];

        return self::paginate($data['list'], $data['page']);
    }


    /**
     * 操作日志列表（游标分页，适用于深分页场景）
     */
    public function listCursor($request)
    {
        $param = $this->validate($request, OperationLogValidate::listCursor());
        $result = $this->dep(OperationLogDep::class)->listByCursor($param);

        // 批量预加载用户数据，避免 N+1
        $userIds = $result['list']->pluck('user_id')->unique()->toArray();
        $userMap = $this->dep(UsersDep::class)->getMap($userIds);

        $list = $result['list']->map(fn($item) => $this->buildLogItem($item, $userMap));

        return self::cursorPaginate($list, $result['next_cursor'], $result['has_more']);
    }

    // ==================== 私有方法 ====================

    /**
     * 组装单条日志数据（关联用户名和邮箱）
     */
    private function buildLogItem($item, $userMap): array
    {
        $user = $userMap->get($item['user_id']);

        return [
            'id'            => $item['id'],
            'user_name'     => $user->username ?? 'Unknown',
            'user_email'    => $user->email ?? '',
            'action'        => $item['action'],
            'request_data'  => $item['request_data'],
            'response_data' => $item['response_data'],
            'is_success'    => $item['is_success'],
            'created_at'    => $item['created_at'],
        ];
    }
}

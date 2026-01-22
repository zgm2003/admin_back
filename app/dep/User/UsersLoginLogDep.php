<?php

namespace app\dep\User;

use app\dep\BaseDep;
use app\model\User\UsersLoginLogModel;
use support\Model;

/**
 * 用户登录日志 Dep
 * 注意：此表没有 is_del 字段，只做记录不删除
 */
class UsersLoginLogDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UsersLoginLogModel();
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', $param['user_id']))
            ->when(!empty($param['login_account']), fn($q) => $q->where('login_account', 'like', $param['login_account'] . '%'))
            ->when(!empty($param['login_type']), fn($q) => $q->where('login_type', $param['login_type']))
            ->when(!empty($param['ip']), fn($q) => $q->where('ip', 'like', $param['ip'] . '%'))
            ->when(!empty($param['platform']), fn($q) => $q->where('platform', 'like', $param['platform'] . '%'))
            ->when(!empty($param['is_success']), fn($q) => $q->where('is_success', $param['is_success']))
            ->when(!empty($param['date']) && is_array($param['date']) && count($param['date']) === 2, fn($q) => $q->whereBetween('created_at', [
                $param['date'][0] . ' 00:00:00',
                $param['date'][1] . ' 23:59:59'
            ]))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 游标分页查询（深分页优化）
     * 注意：此表没有 is_del 字段，传 checkDel = false
     */
    public function listByCursor(array $param): array
    {
        $columns = ['id', 'user_id', 'login_account', 'login_type', 'platform', 'ip', 'ua', 'is_success', 'reason', 'created_at'];
        
        return $this->listCursor($param, function ($q) use ($param) {
            $q->when(!empty($param['user_id']), fn($q) => $q->where('user_id', $param['user_id']))
              ->when(!empty($param['login_account']), fn($q) => $q->where('login_account', 'like', $param['login_account'] . '%'))
              ->when(!empty($param['login_type']), fn($q) => $q->where('login_type', $param['login_type']))
              ->when(!empty($param['ip']), fn($q) => $q->where('ip', 'like', $param['ip'] . '%'))
              ->when(!empty($param['platform']), fn($q) => $q->where('platform', 'like', $param['platform'] . '%'))
              ->when(!empty($param['is_success']), fn($q) => $q->where('is_success', $param['is_success']))
              ->when(!empty($param['date']) && is_array($param['date']) && count($param['date']) === 2, fn($q) => $q->whereBetween('created_at', [
                  $param['date'][0] . ' 00:00:00',
                  $param['date'][1] . ' 23:59:59'
              ]));
        }, $columns, false);  // checkDel = false
    }

    /**
     * 覆盖父类方法：此表没有 is_del 字段
     */
    public function get(int $id)
    {
        return $this->find($id);
    }
}

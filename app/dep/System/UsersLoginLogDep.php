<?php

namespace app\dep\System;

use app\model\System\UsersLoginLogModel;

class UsersLoginLogDep
{
    public $model;

    public function __construct()
    {
        $this->model = new UsersLoginLogModel();
    }

    public function add(array $data): int
    {
        return (int) $this->model->insertGetId($data);
    }

    public function list($param)
    {
        return $this->model
            ->when(!empty($param['user_id']), function ($query) use ($param) {
                $query->where('user_id', $param['user_id']);
            })
            ->when(!empty($param['login_account']), function ($query) use ($param) {
                $query->where('login_account', 'like', "%{$param['login_account']}%");
            })
            ->when(!empty($param['login_type']), function ($query) use ($param) {
                $query->where('login_type', $param['login_type']);
            })
            ->when(!empty($param['ip']), function ($query) use ($param) {
                $query->where('ip', 'like', "%{$param['ip']}%");
            })
            ->when(!empty($param['platform']), function ($query) use ($param) {
                $query->where('platform', 'like', "%{$param['platform']}%");
            })
            ->when(!empty($param['is_success']), function ($query) use ($param) {
                $query->where('is_success', $param['is_success']);
            })
            ->when(!empty($param['date']), function ($query) use ($param) {
                if (is_array($param['date']) && count($param['date']) === 2) {
                    $query->whereBetween('created_at', [
                        $param['date'][0] . ' 00:00:00',
                        $param['date'][1] . ' 23:59:59'
                    ]);
                }
            })
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }
}

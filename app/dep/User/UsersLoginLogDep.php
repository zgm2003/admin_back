<?php

namespace app\dep\User;

use app\model\User\UsersLoginLogModel;

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
            ->when(!empty($param['email']), function ($query) use ($param) {
                $query->where('email', 'like', "%{$param['email']}%");
            })
            ->when(!empty($param['ip']), function ($query) use ($param) {
                $query->where('ip', 'like', "%{$param['ip']}%");
            })
            ->when(!empty($param['platform']), function ($query) use ($param) {
                $query->where('platform', 'like', "%{$param['platform']}%");
            })
             ->when(isset($param['success']) && $param['success'] !== '', function ($query) use ($param) {
                $query->where('success', $param['success']);
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

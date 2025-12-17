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
}

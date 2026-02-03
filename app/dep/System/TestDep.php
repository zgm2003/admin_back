<?php

namespace app\dep\System;

use app\dep\BaseDep;
use app\model\System\TestModel;
use app\enum\CommonEnum;
use support\Model;

class TestDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new TestModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->when(!empty(trim($param['title'] ?? '')), fn($q) => $q->where('title', 'like', trim($param['title']) . '%'))
            ->when(!empty(trim($param['username'] ?? '')), fn($q) => $q->where('username', 'like', trim($param['username']) . '%'))
            ->when(!empty(trim($param['nickname'] ?? '')), fn($q) => $q->where('nickname', 'like', trim($param['nickname']) . '%'))
            ->when(!empty(trim($param['email'] ?? '')), fn($q) => $q->where('email', 'like', trim($param['email']) . '%'))
            ->when(isset($param['status']) && $param['status'] !== '' && $param['status'] !== null, fn($q) => $q->where('status', $param['status']))
            ->when(isset($param['type']) && $param['type'] !== '' && $param['type'] !== null, fn($q) => $q->where('type', $param['type']))
            ->when(isset($param['sex']) && $param['sex'] !== '' && $param['sex'] !== null, fn($q) => $q->where('sex', $param['sex']))
            ->when(isset($param['is_vip']) && $param['is_vip'] !== '' && $param['is_vip'] !== null, fn($q) => $q->where('is_vip', $param['is_vip']))
            ->when(isset($param['is_hot']) && $param['is_hot'] !== '' && $param['is_hot'] !== null, fn($q) => $q->where('is_hot', $param['is_hot']))
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }
}
<?php

namespace app\dep;

use app\model\TestModel;
use app\enum\CommonEnum;
use support\Model;

class TestDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new TestModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据名称查询
     */
    public function findByName(string $name)
    {
        return $this->model->where('name', $name)->first();
    }

    /**
     * 根据手机号查询
     */
    public function findByMobile(string $mobile)
    {
        return $this->model->where('mobile', $mobile)->first();
    }

    /**
     * 获取所有记录
     */
    public function all()
    {
        return $this->model->all();
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['username']), fn($q) => $q->where('username', 'like', "%{$param['username']}%"))
            ->when(!empty($param['nickname']), fn($q) => $q->where('nickname', 'like', "%{$param['nickname']}%"))
            ->when(!empty($param['status']), fn($q) => $q->where('status', $param['status']))
            ->when(!empty($param['platform']), fn($q) => $q->where('platform', $param['platform']))
            ->when(!empty($param['platform_id']), fn($q) => $q->where('platform_id', $param['platform_id']))
            ->when(!empty($param['mobile_id']), fn($q) => $q->where('mobile_id', $param['mobile_id']))
            ->when(!empty($param['legal_type']), fn($q) => $q->where('legal_type', $param['legal_type']))
            ->when(!empty($param['date']) && is_array($param['date']) && count($param['date']) === 2, fn($q) => $q->whereBetween('register_at', [$param['date'][0], $param['date'][1]]))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }
}

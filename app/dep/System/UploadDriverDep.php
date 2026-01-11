<?php

namespace app\dep\System;

use app\dep\BaseDep;
use app\model\System\UploadDriverModel;
use app\enum\CommonEnum;
use support\Model;

class UploadDriverDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UploadDriverModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据 driver + bucket 查询
     */
    public function findByDriverBucket(string $driver, string $bucket)
    {
        return $this->model
            ->where('driver', $driver)
            ->where('bucket', $bucket)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 获取字典列表
     */
    public function getDict()
    {
        return $this->model
            ->select(['id', 'driver', 'bucket'])
            ->where('is_del', CommonEnum::NO)
            ->get();
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->when(!empty($param['driver']), fn($q) => $q->where('driver', $param['driver']))
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }
}

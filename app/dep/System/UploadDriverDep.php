<?php

namespace app\dep\System;

use app\model\System\UploadDriverModel;
use app\enum\CommonEnum;

class UploadDriverDep
{
    public $model;

    public function __construct()
    {
        $this->model = new UploadDriverModel();
    }

    public function first($id)
    {
        return $this->model->where('id', $id)->first();
    }

    public function add($data)
    {
        return $this->model->insertGetId($data);
    }

    public function edit($id, $data)
    {
        if (!is_array($id)) $id = [$id];
        return $this->model->whereIn('id', $id)->update($data);
    }

    public function del($id, $data)
    {
        if (!is_array($id)) $id = [$id];
        return $this->model->whereIn('id', $id)->update($data);
    }

    public function list($param)
    {
        $pageSize = $param['page_size'];
        $currentPage = $param['current_page'];
        return $this->model
            ->when(!empty($param['driver']), function ($query) use ($param) {
                $query->where('driver', $param['driver']);
            })
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }
}

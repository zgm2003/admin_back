<?php

namespace app\dep\System;


use app\model\System\OperationLogModel;
use Carbon\Carbon;

class OperationLogDep
{
    public $model;

    public function __construct()
    {
        $this->model = new OperationLogModel();
    }

    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }

    public function firstByName($name)
    {
        $res = $this->model->where('name', $name)->first();
        return $res;
    }

    public function firstByMobile($mobile)
    {
        $res = $this->model->where('mobile', $mobile)->first();
        return $res;
    }

    public function all()
    {

        $res = $this->model->all();

        return $res;
    }

    public function add($data)
    {
        $res = $this->model->insertGetId($data);
        return $res;
    }

    public function edit($id, $data)
    {
        if (!is_array($id)) {
            $id = [$id];
        }
        $res = $this->model->whereIn('id', $id)->update($data);
        return $res;
    }

    public function batchEdit($ids, $data)
    {
        $res = $this->model->whereIn('id', $ids)->update($data);
        return $res;
    }

    public function del($id)
    {
        if (!is_array($id)) {
            $id = [$id];
        }
        $res = $this->model->whereIn('id', $id)->delete();
        return $res;
    }


    public function list($param)
    {
        $res = $this->model
            ->when(!empty($param['action']), function ($query) use ($param) {
                $query->where('action', 'like', "%{$param['action']}%");
            })
            ->when(!empty($param['user_id']), function ($query) use ($param) {
                $query->where('user_id', $param['user_id']);
            })
            ->when(!empty($param['date'])
                && is_array($param['date'])
                && count($param['date']) === 2,
                function ($query) use ($param) {
                    $start = Carbon::parse($param['date'][0])->startOfDay()->toDateTimeString(); // 加上 00:00:00
                    $end = Carbon::parse($param['date'][1])->endOfDay()->toDateTimeString();   // 加上 23:59:59

                    $query->whereBetween('created_at', [$start, $end]);
                })
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);

        return $res;
    }

}

<?php

namespace app\dep;


use app\model\AddressModel;
use app\enum\CommonEnum;

class AddressDep
{
    public $model;

    public function __construct()
    {
        $this->model = new AddressModel();
    }

    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }

    public function firstByName($name){
        $res = $this->model->where('name',$name)->first();
        return $res;
    }

    public function firstByMobile($mobile){
        $res = $this->model->where('mobile',$mobile)->first();
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
        if(!is_array($id)){
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

    public function del($id, $data)
    {
        if(!is_array($id)){
            $id = [$id];
        }
        $res = $this->model->whereIn('id', $id)->update($data);
        return $res;
    }


    public function list($param){
        $res = $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['username']), function ($query) use ($param) {
                $query->where('username','like' ,"%{$param['username']}%");
            })
            ->when(!empty($param['nickname']), function ($query) use ($param) {
                $query->where('nickname','like' ,"%{$param['nickname']}%");
            })
            ->when(!empty($param['status']), function ($query) use ($param) {
                $query->where('status', $param['status']);
            })
            ->when(!empty($param['platform']), function ($query) use ($param) {
                $query->where('platform', $param['platform']);
            })
            ->when(!empty($param['platform_id']), function ($query) use ($param) {
                $query->where('platform_id', $param['platform_id']);
            })
            ->when(!empty($param['mobile_id']), function ($query) use ($param) {
                $query->where('mobile_id', $param['mobile_id']);
            })
            ->when(!empty($param['legal_type']), function ($query) use ($param) {
                $query->where('legal_type', $param['legal_type']);
            })
            ->when(!empty($param['date']), function ($query) use ($param) {
                // 假设 date 参数是一个包含两个日期的数组
                if (is_array($param['date']) && count($param['date']) === 2) {
                    $query->whereBetween('register_at', [$param['date'][0], $param['date'][1]]);
                }
            })
            ->orderBy('id','desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);

        return $res;
    }

}

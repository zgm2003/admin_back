<?php

namespace app\dep\User;

use app\enum\CommonEnum;
use app\model\User\UsersModel;

class UsersDep
{
    public $model;

    public function __construct()
    {
        $this->model = new UsersModel();
    }

    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }

    public function firstByEmail($email){
        $res = $this->model->where('email',$email)->first();
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
            ->when(!empty($param['email']), function ($query) use ($param) {
                $query->where('email','like' ,"%{$param['email']}%");
            })
            ->when(!empty($param['detail_address']), function ($query) use ($param) {
                $query->where('detail_address','like' ,"%{$param['detail_address']}%");
            })
            ->when(!empty($param['address']), function ($query) use ($param) {
                $lastAddress = end($param['address']);
                $query->where('address', 'like', '%' . $lastAddress . '%');
            })

            ->when(!empty($param['role_id']), function ($query) use ($param) {
                $query->where('role_id', $param['role_id']);
            })
            ->when(!empty($param['sex']), function ($query) use ($param) {
                $query->where('sex', $param['sex']);
            })

            ->when(!empty($param['date']), function ($query) use ($param) {
                // 假设 date 参数是一个包含两个日期的数组
                if (is_array($param['date']) && count($param['date']) === 2) {
                    $query->whereBetween('register_at', [$param['date'][0], $param['date'][1]]);
                }
            })
//            ->orderBy('id','desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);

        return $res;
    }

    public function getByUsername($username)
    {
        $res = $this->model->where('username','like', "%{$username}%")->get();
        return $res;
    }


}

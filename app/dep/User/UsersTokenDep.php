<?php

namespace app\dep\User;

use app\model\User\UsersTokenModel;

class UsersTokenDep
{
    public $model;

    public function __construct()
    {
        $this->model = new UsersTokenModel();
    }

    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }

    public function add($data)
    {
        $res = $this->model->insertGetId($data);
        return $res;
    }

    public function editByUserId($id, $data)
    {
        if(!is_array($id)){
            $id = [$id];
        }
        $res = $this->model->whereIn('user_id', $id)->update($data);
        return $res;
    }

    public function firstByToken($token){
        $res = $this->model->where('token',$token)->first();
        return $res;
    }

    public function clearIpByToken(mixed $token)
    {
        $res = $this->model->where('token', $token)->update([
            'ip' => null,
        ]);
        return $res;
    }

    public function editByToken($token, $data)
    {
        $res = $this->model->where('token', $token)->update($data);
        return $res;
    }

    public function firstByUserId($id)
    {
        $res = $this->model->where('user_id', $id)->first();
        return $res;
    }

    public function firstByUserIdAndPlatform($userId, $platform)
    {
        $res = $this->model->where('user_id', $userId)->where('platform', $platform)->first();
        return $res;
    }

    public function editByUserIdAndPlatform($userId, $platform, $data)
    {
        $res = $this->model->where('user_id', $userId)->where('platform', $platform)->update($data);
        return $res;
    }


}

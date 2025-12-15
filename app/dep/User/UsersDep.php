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

    public function list($param)
    {
        return $this->model
            ->from('users as u')
            ->leftJoin('user_profiles as up', 'u.id', '=', 'up.user_id')
            ->where('u.is_del', CommonEnum::NO)

            ->when(isset($param['username']) && $param['username'] !== '', function ($q) use ($param) {
                $q->where('u.username', 'like', '%' . $param['username'] . '%');
            })
            ->when(isset($param['email']) && $param['email'] !== '', function ($q) use ($param) {
                $q->where('u.email', 'like', '%' . $param['email'] . '%');
            })
            ->when(isset($param['detail_address']) && $param['detail_address'] !== '', function ($q) use ($param) {
                $q->where('up.detail_address', 'like', '%' . $param['detail_address'] . '%');
            })
            ->when(!empty($param['address_id'] ?? $param['address'] ?? null), function ($q) use ($param) {
                $ids = $param['address_id'] ?? $param['address'];
                if (is_array($ids)) {
                    $q->whereIn('up.address_id', array_map('intval', $ids));
                } else {
                    $q->where('up.address_id', (int)$ids);
                }
            })
            ->when(isset($param['role_id']) && $param['role_id'] !== '', function ($q) use ($param) {
                $q->where('u.role_id', (int)$param['role_id']);
            })
            ->when(isset($param['sex']) && $param['sex'] !== '', function ($q) use ($param) {
                $q->where('up.sex', (int)$param['sex']);
            })
            ->when(!empty($param['date']) && is_array($param['date']) && count($param['date']) === 2, function ($q) use ($param) {
                $q->whereBetween('u.created_at', [$param['date'][0], $param['date'][1]]);
            })

            ->select([
                'u.id','u.username','u.email','u.phone','u.role_id','u.status','u.created_at','u.updated_at',
                'up.avatar','up.sex','up.address_id','up.detail_address',
            ])
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }


    public function getByUsername($username)
    {
        $res = $this->model->where('username','like', "%{$username}%")->get();
        return $res;
    }

    public function all()
    {
        $res = $this->model->select(['id','username','email'])->get();
        return $res;

    }

    public function getUsersByIds(array $ids)
    {
        $res = $this->model->whereIn('id', $ids)->get();
        return $res;
    }


}

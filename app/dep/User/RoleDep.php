<?php

namespace app\dep\User;


use app\enum\CommonEnum;
use app\model\User\RoleModel;

class RoleDep
{
    public $model;

    public function __construct()
    {
        $this->model = new RoleModel();
    }

    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }
    public function firstBySuperAdmin()
    {
        $res = $this->model->where('name', '超级管理员')->where('is_del', CommonEnum::NO)->first();
        return $res;
    }
    public function firstByAdmin()
    {
        $res = $this->model->where('name', '管理员')->where('is_del', CommonEnum::NO)->first();
        return $res;
    }
     public function firstByName($name)
    {
        $res = $this->model->where('name', $name)->where('is_del', CommonEnum::NO)->first();
        return $res;
    }

    public function getUserByToken($token)
    {
        $res = $this->model->where('token', $token)->first();
        return $res;
    }
    public function firstByEmail($email){
        $res = $this->model->where('email',$email)->first();
        return $res;
    }
    public function firstByToken($token){
        $res = $this->model->where('token',$token)->first();
        return $res;
    }

    public function all()
    {

        $res = $this->model->all();

        return $res;
    }
    public function allOK()
    {

        $res = $this->model->where('is_del', CommonEnum::NO)->get();

        return $res;
    }
    public function firstByDefault()
    {
        $res = $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('is_default', CommonEnum::YES)
            ->first();
        return $res;
    }
    public function clearDefault()
    {
        return $this->model
            ->where('is_default', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_default' => CommonEnum::NO]);
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

    public function getByIds(array $ids, bool $onlyActive = false)
    {
        $query = $this->model->whereIn('id', $ids);
        if ($onlyActive) {
            $query->where('is_del', CommonEnum::NO);
        }
        return $query->get();
    }

    public function hasDefaultIn(array $ids): bool
    {
        return $this->model
            ->whereIn('id', $ids)
            ->where('is_del', CommonEnum::NO)
            ->where('is_default', CommonEnum::YES)
            ->exists();
    }

    public function list($param){
        $res = $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['name']), function ($query) use ($param) {
                $query->where('name','like' ,"%{$param['name']}%");
            })
            ->orderBy('id','asc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);

        return $res;
    }

    /**
     * 批量获取角色(按ID列表)
     * @param array $ids
     * @return \Illuminate\Support\Collection  id => RoleModel
     */
    public function getMapByIds(array $ids)
    {
        if (empty($ids)) return collect();
        return $this->model
            ->whereIn('id', array_unique($ids))
            ->get()
            ->keyBy('id');
    }

}

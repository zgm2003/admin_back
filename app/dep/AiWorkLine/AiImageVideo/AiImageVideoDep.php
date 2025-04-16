<?php
namespace app\dep\AiWorkLine\AiImageVideo;

use app\model\AiWorkLine\AiImageVideo\AiImageVideoModel;
use app\Enum\CommonEnum;

class AiImageVideoDep
{
    public $model;

    public function __construct()
    {
        $this->model = new AiImageVideoModel();
    }
    public function census() {
        $res = $this->model
            ->selectRaw('COUNT(id) AS num, status')
            ->where('is_del', CommonEnum::NO)
            ->groupBy('status')
            ->get();

        return $res;
    }
    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }
    public function firstByGoodsIdAndPlatform($goods_id,$platform)
    {
        $res = $this->model->where('goods_id', $goods_id)->where('platform',$platform)->where('is_del', CommonEnum::NO)->first();
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
            ->when(!empty($param['status']), function ($query) use ($param) {
                $query->where('status',$param['status']);
            })
            ->when(!empty($param['name']), function ($query) use ($param) {
                $query->where('task_id',$param['name']);
            })
            ->when(!empty($param['title']), function ($query) use ($param) {
                $query->where('title','like' ,"%{$param['title']}%");
            })

            ->orderBy('id','desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);

        return $res;
    }

}

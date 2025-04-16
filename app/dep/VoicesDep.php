<?php

namespace app\dep;

use app\model\VoicesModel;
use app\enum\CommonEnum;

class VoicesDep
{
    public $model;

    public function __construct()
    {
        $this->model = new VoicesModel();
    }

    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }
    public function allByUser($userId)
    {

        $res = $this->model->where('created_user_id', $userId)->where('is_del', CommonEnum::NO)->get();

        return $res;
    }
    public function firstByTitleAndCategoryId($title,$id)
    {
        $res = $this->model->where('title', $title)->where('category_id',$id)->where('is_del', CommonEnum::NO)->first();
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
            ->when(!empty($param['name']), function ($query) use ($param) {
                $query->where('name','like' ,"%{$param['name']}%");
            })
            ->when(!empty($param['code']), function ($query) use ($param) {
                $query->where('code','like' ,"%{$param['code']}%");
            })
            ->when(!empty($param['voice_style']), function ($query) use ($param) {
                $query->where('voice_style','like' ,"%{$param['voice_style']}%");
            })
            ->when(!empty($param['quality']), function ($query) use ($param) {
                $query->where('quality' ,$param['quality']);
            })
            ->when(!empty($param['supported_scene']), function ($query) use ($param) {
                $query->where('supported_scene','like' ,"%{$param['supported_scene']}%");
            })
            ->when(!empty($param['sampling_rates']), function ($query) use ($param) {
                $query->where('sampling_rates' ,$param['sampling_rates']);
            })
            ->orderBy('id','asc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);

        return $res;
    }

}

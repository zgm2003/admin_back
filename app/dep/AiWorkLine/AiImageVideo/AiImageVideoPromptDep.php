<?php

namespace app\dep\AiWorkLine\AiImageVideo;


use app\model\AiWorkLine\AiImageVideo\AiImageVideoPromptModel;
use app\enum\CommonEnum;

class AiImageVideoPromptDep
{
    public $model;

    public function __construct()
    {
        $this->model = new AiImageVideoPromptModel();
    }

    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }

     public function firstByName($name)
    {
        $res = $this->model->where('name', $name)->where('is_del', CommonEnum::NO)->first();
        return $res;
    }
    public function firstByTitle($title)
    {
        $res = $this->model->where('title', $title)->where('is_del', CommonEnum::NO)->first();
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

    public function del($id)
    {
        if (!is_array($id)) {
            $id = [$id]; // 如果传入的是单个 ID，转换成数组
        }

        // 使用 delete() 方法删除数据
        $res = $this->model->whereIn('id', $id)->delete(); // 删除操作
        return $res;
    }



    public function list($param){
        $res = $this->model
            ->where("is_del", CommonEnum::NO)
            ->when(!empty($param['title']), function ($query) use ($param) {
                $query->where('title','like' ,"%{$param['title']}%");
            })
            ->orderBy('id','asc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);

        return $res;
    }

}

<?php

namespace app\dep\Ai;

use app\model\Ai\AiModel;
use app\enum\CommonEnum;

class AiDep
{
    public $model;

    public function __construct()
    {
        $this->model = new AiModel();
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
            ->when(!empty($param['title']), function ($query) use ($param) {
                $query->where('title','like' ,"%{$param['title']}%");
            })
            ->when(!empty($param['category_id']), function ($query) use ($param) {
                $query->where('category_id',$param['category_id']);
            })
            ->orderBy('id','asc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);

        return $res;
    }

    public function list1($param){
        $res = $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['title']), function ($query) use ($param) {
                $query->where('title','like' ,"%{$param['title']}%");
            })
            ->when(!empty($param['category_id']), function ($query) use ($param) {
                $query->where('category_id',$param['category_id']);
            })
            ->get();

        return $res;
    }
    public function listByCategory(array $param, int $categoryId, int $limit = 5)
    {
        $query = $this->model
            ->select(['id','title','url','link','desc','created_at'])
            ->where('is_del', CommonEnum::NO)
            ->where('category_id', $categoryId);

        // 可选：按标题模糊
        if (!empty($param['title'])) {
            $query->where('title', 'like', "%{$param['title']}%");
        }
        // 可选：前端指定分类时，进一步过滤
        if (!empty($param['category_id'])) {
            $query->where('category_id', $param['category_id']);
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    // app/dep/Ai/AiDep.php
    public function listByCategoryPaginated(array $param, int $categoryId, int $perPage = 20, int $page = 1)
    {
        $query = $this->model
            ->select(['id','title','url','link','desc','created_at'])
            ->where('is_del', CommonEnum::NO)
            ->where('category_id', $categoryId);

        if (!empty($param['title'])) {
            $query->where('title', 'like', "%{$param['title']}%");
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

}

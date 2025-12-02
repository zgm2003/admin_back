<?php

namespace app\dep\User;

use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\model\User\PermissionModel;

class PermissionDep
{
    public $model;

    public function __construct()
    {
        $this->model = new PermissionModel();
    }

    public function firstByParentCategory($ParentCategory)
    {
        $res = $this->model->where('name', $ParentCategory)->where('is_del', CommonEnum::NO)->first();
        return $res;
    }

    public function firstByChildCategory($parent_id, $name)
    {
        $res = $this->model->where('name', $name)->where('parent_id', $parent_id)->where('is_del', CommonEnum::NO)->first();
        return $res;
    }

    public function firstByPath($path)
    {
        $res = $this->model->where('path', $path)->where('is_del', CommonEnum::NO)->first();
        return $res;
    }

    public function allParent()
    {

        $res = $this->model->where('parent_id', PermissionEnum::ParentCategory)->where('is_del', CommonEnum::NO)->get();

        return $res;
    }

    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }

    public function firstOK($id)
    {
        $res = $this->model->where('id', $id)->where('is_del', CommonEnum::NO)->first();
        return $res;
    }

    public function getByRouter($ids)
    {
        $res = $this->model
            ->whereIn('id', $ids)
            ->whereNotNull('path')
            ->whereNotNull('component')
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->get();
        return $res;
    }

    public function firstByName($name)
    {
        $res = $this->model->where('name', $name)->where('is_del', CommonEnum::NO)->first();
        return $res;
    }

    public function all()
    {

        $res = $this->model->all();

        return $res;
    }

    public function allOK()
    {

        $res = $this->model->where('is_del', CommonEnum::NO)->where('status', CommonEnum::YES)->get();

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

    public function del($id, $data)
    {
        if (!is_array($id)) {
            $id = [$id];
        }
        $res = $this->model->whereIn('id', $id)->update($data);
        return $res;
    }

    public function list($param)
    {
        $res = $this->model
            ->when(!empty($param['name']), function ($query) use ($param) {
                $query->where('name', 'like', "%{$param['name']}%");
            })
            ->when(!empty($param['path']), function ($query) use ($param) {
                $query->where('path', 'like', "%{$param['path']}%");
            })
            ->where('is_del', CommonEnum::NO)
            ->get();

        return $res;
    }

    public function getAllPermissions()
    {
        $permissions = $this->model
            ->select(['id', 'name', 'parent_id', 'path', 'component', 'icon', 'code', 'type','i18n_key'])
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->get()->toArray();
        return $permissions;
    }




}

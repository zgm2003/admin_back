<?php

namespace app\service;

use app\dep\AddressDep;
use app\dep\User\RoleDep;
use app\dep\User\PermissionDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\enum\SexEnum;
use app\enum\UploadConfigEnum;


class DictService
{
    public $dict = [];

    public function setSexArr(){
        $this->dict['sexArr'] = $this->enumToDict(SexEnum::$SexArr);
        return $this;
    }
    public function setPermissionTree()
    {

        $dep = new PermissionDep();

        $resCategory = $dep->allOK()->map(function ($item) {
            return [
                'id' => $item->id,
                'label' => $item->name,
                'value' => $item->id,
                'parent_id' => $item->parent_id,
            ];
        });
        $this->dict['permission_tree'] = listToTree($resCategory->toArray(), -1);
        return $this;
    }

    public function setAuthAdressTree()
    {

        $dep = new AddressDep();

        $resCategory = $dep->all()->map(function ($item) {
            return [
                'id' => $item->id,
                'label' => $item->name,
                'value' => $item->id,
                'parent_id' => $item->parent_id,
            ];
        });
        $this->dict['auth_address_tree'] = listToTree($resCategory->toArray(), -1);
        return $this;
    }
    public function setRoleArr()
    {
        $roleDep = new RoleDep();
        $res = $roleDep->allOK();
        // 遍历集合并处理每个元素
        $this->dict['roleArr'] = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->name,
            ];
        });
        return $this;
    }
    public function setPermissionTypeArr(){
        $this->dict['permission_type_arr'] = $this->enumToDict(PermissionEnum::$typeArr);
        return $this;
    }
    public function setCommonStatusArr(){
        $this->dict['common_status_arr'] = $this->enumToDict(CommonEnum::$statusArr);
        return $this;
    }
    public function setUploadImageExtArr(){
        $this->dict['upload_image_ext_arr'] = $this->enumToDict(UploadConfigEnum::$imageExtArr);
        return $this;
    }
    public function setUploadFileExtArr(){
        $this->dict['upload_file_ext_arr'] = $this->enumToDict(UploadConfigEnum::$fileExtArr);
        return $this;
    }
    public function setUploadDriverArr(){
        $this->dict['upload_driver_arr'] = $this->enumToDict(UploadConfigEnum::$driverArr);
        return $this;
    }
    public function setUserArr()
    {
        $dep = new UsersDep();
        $res = $dep->all();
        // 遍历集合并处理每个元素
        $this->dict['usernameArr'] = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->username,
            ];
        });
        $this->dict['emailArr'] = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->email,
            ];
        });
        return $this;
    }


    public function enumToDict($enum)
    {
        $res = [];
        foreach ($enum as $index => $item) {
            $res[] = [
                'label' => $item,
                'value' => $index,
            ];
        }
        return $res;
    }
    public function getDict()
    {
        return $this->dict;
    }
}

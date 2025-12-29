<?php

namespace app\service;

use app\dep\AddressDep;
use app\dep\System\UploadDriverDep;
use app\dep\System\UploadRuleDep;
use app\dep\User\RoleDep;
use app\dep\User\PermissionDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\enum\SexEnum;
use app\enum\UploadConfigEnum;
use app\enum\SystemEnum;
use support\Cache;


class DictService
{
    public $dict = [];
    
    // Redis 缓存Key
    const CACHE_KEY_PERMISSION_TREE = 'dict_permission_tree';
    const CACHE_KEY_ADDRESS_TREE = 'dict_address_tree';
    const CACHE_TTL = 300; // 5分钟

    /**
     * 清除所有缓存
     */
    public static function clearCache(): void
    {
        Cache::delete(self::CACHE_KEY_PERMISSION_TREE);
        Cache::delete(self::CACHE_KEY_ADDRESS_TREE);
    }

    /**
     * 清除权限树缓存
     */
    public static function clearPermissionCache(): void
    {
        Cache::delete(self::CACHE_KEY_PERMISSION_TREE);
    }

    /**
     * 清除地址缓存（地址数据变更时手动调用）
     */
    public static function clearAddressCache(): void
    {
        Cache::delete(self::CACHE_KEY_ADDRESS_TREE);
    }

    public function setLoginTypeArr(){
        $this->dict['login_type_arr'] = $this->enumToDict(SystemEnum::$loginTypeArr);
        return $this;
    }
    public function setSexArr(){
        $this->dict['sexArr'] = $this->enumToDict(SexEnum::$SexArr);
        return $this;
    }
    public function setPermissionTree()
    {
        // 尝试从Redis缓存获取树形结构
        $cached = Cache::get(self::CACHE_KEY_PERMISSION_TREE);
        if ($cached !== null) {
            $this->dict['permission_tree'] = $cached;
            return $this;
        }
        
        // 复用 PermissionDep 的缓存数据，避免重复DB查询
        $dep = new PermissionDep();
        $allPermissions = $dep->getAllPermissions();
        
        // 转换为树形结构
        $resCategory = array_map(function ($item) {
            return [
                'id' => $item['id'],
                'label' => $item['name'],
                'value' => $item['id'],
                'parent_id' => $item['parent_id'],
            ];
        }, $allPermissions);
        $tree = listToTree($resCategory, -1);
        
        Cache::set(self::CACHE_KEY_PERMISSION_TREE, $tree, self::CACHE_TTL);
        $this->dict['permission_tree'] = $tree;
        
        return $this;
    }

    public function setAuthAdressTree()
    {
        // 尝试从Redis缓存获取树形结构
        $cached = Cache::get(self::CACHE_KEY_ADDRESS_TREE);
        if ($cached !== null) {
            $this->dict['auth_address_tree'] = $cached;
            return $this;
        }

        // 复用 AddressDep 的缓存数据，避免重复DB查询
        $dep = new AddressDep();
        $allAddressMap = $dep->getAllMap();
        
        // 转换为树形结构
        $resCategory = array_map(function ($item) {
            return [
                'id' => $item['id'],
                'label' => $item['name'],
                'value' => $item['id'],
                'parent_id' => $item['parent_id'],
            ];
        }, $allAddressMap);
        $tree = listToTree($resCategory, -1);
        
        // 永久缓存（不设TTL）
        Cache::set(self::CACHE_KEY_ADDRESS_TREE, $tree);
        $this->dict['auth_address_tree'] = $tree;
        
        return $this;
    }
    public function setRoleArr()
    {
        // role表数据量小，直接查询无需缓存
        $roleDep = new RoleDep();
        $res = $roleDep->allOK();
        $arr = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->name,
            ];
        });
        
        $this->dict['roleArr'] = $arr;
        
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
    public function setUploadDriverList(){
        $dep = new UploadDriverDep();
        $this->dict['upload_driver_list'] = $dep->setDict()->map(function($item){
            return ['label' => $item->driver . ' - ' . $item->bucket, 'value' => $item->id];
        });
        return $this;
    }
    public function setUploadRuleList(){
        $dep = new UploadRuleDep();
        $this->dict['upload_rule_list'] = $dep->setDict()->map(function($item){
            return ['label' => $item->title, 'value' => $item->id];
        });
        return $this;
    }
    public function setSystemSettingValueTypeArr(){
        $this->dict['system_setting_value_type_arr'] = $this->enumToDict(SystemEnum::$valueTypeArr);
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

    public function setPlatformArr(){
        $this->dict['platformArr'] = $this->enumToDict(CommonEnum::$platformArr);
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

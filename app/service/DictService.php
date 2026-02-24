<?php

namespace app\service;

use app\dep\AddressDep;
use app\dep\System\UploadDriverDep;
use app\dep\System\UploadRuleDep;
use app\dep\Permission\RoleDep;
use app\dep\Permission\PermissionDep;
use app\dep\Ai\AiAgentsDep;
use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\enum\UploadConfigEnum;
use app\enum\SystemEnum;
use app\enum\AiEnum;
use app\enum\GoodsEnum;
use app\enum\CronEnum;
use app\enum\NotificationEnum;
use app\service\System\AuthPlatformService;
use support\Cache;


class DictService
{
    public $dict = [];
    
    // Redis 缓存Key
    const CACHE_KEY_PERMISSION_TREE = 'dict_permission_tree';
    const CACHE_KEY_ADDRESS_TREE = 'dict_address_tree';

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

    public function setVerifyTypeArr(){
        $this->dict['verify_type_arr'] = $this->enumToDict(SystemEnum::$verifyTypeArr);
        return $this;
    }
    public function setSexArr(){
        $this->dict['sexArr'] = $this->enumToDict(CommonEnum::$sexArr);
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
        
        // 转换为树形结构，label 加上平台标识，保留 platform 字段供前端过滤
        $platformMap = AuthPlatformService::getPlatformMap();
        $resCategory = array_map(function ($item) use ($platformMap) {
            $platform = $item['platform'] ?? '';
            $platformTag = $platform ? '[' . ($platformMap[$platform] ?? $platform) . '] ' : '';
            return [
                'id' => $item['id'],
                'label' => $platformTag . $item['name'],
                'value' => $item['id'],
                'parent_id' => $item['parent_id'],
                'platform' => $platform,  // 保留平台字段供前端过滤
            ];
        }, $allPermissions);
        $tree = listToTree($resCategory, -1);
        
        Cache::set(self::CACHE_KEY_PERMISSION_TREE, $tree); // 永久缓存
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
        $res = $roleDep->allActive();
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
    public function setPermissionPlatformArr(){
        $this->dict['permission_platform_arr'] = $this->enumToDict(AuthPlatformService::getPlatformMap());
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
        $this->dict['upload_driver_list'] = $dep->getDict()->map(function($item){
            return ['label' => $item->driver . ' - ' . $item->bucket, 'value' => $item->id];
        });
        return $this;
    }
    public function setUploadRuleList(){
        $dep = new UploadRuleDep();
        $this->dict['upload_rule_list'] = $dep->getDict()->map(function($item){
            return ['label' => $item->title, 'value' => $item->id];
        });
        return $this;
    }
    public function setSystemSettingValueTypeArr(){
        $this->dict['system_setting_value_type_arr'] = $this->enumToDict(SystemEnum::$valueTypeArr);
        return $this;
    }

    public function setPlatformArr(){
        $this->dict['platformArr'] = $this->enumToDict(AuthPlatformService::getPlatformMap());
        return $this;
    }

    public function setAiDriverArr(){
        $this->dict['ai_driver_arr'] = $this->enumToDict(AiEnum::$driverArr);
        return $this;
    }

    public function setAiModeArr(){
        $this->dict['ai_mode_arr'] = $this->enumToDict(AiEnum::$modeArr);
        return $this;
    }

    public function setAiSceneArr(){
        $this->dict['ai_scene_arr'] = $this->enumToDict(AiEnum::$sceneArr);
        return $this;
    }

    public function setGoodsPlatformArr(){
        $this->dict['goods_platform_arr'] = $this->enumToDict(GoodsEnum::$platformArr);
        return $this;
    }

    public function setGoodsStatusArr(){
        $this->dict['goods_status_arr'] = $this->enumToDict(GoodsEnum::$statusArr);
        return $this;
    }

    public function setGoodsVoiceArr(){
        $this->dict['goods_voice_arr'] = $this->enumToDict(GoodsEnum::$voiceArr);
        return $this;
    }

    public function setRunStatusArr(){
        $this->dict['run_status_arr'] = $this->enumToDict(AiEnum::$runStatusArr);
        return $this;
    }

    public function setAgentArr(){
        $dep = new AiAgentsDep();
        $res = $dep->allActive();
        $this->dict['agentArr'] = $res->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->name,
            ];
        });
        return $this;
    }

    public function setCronPresetArr(){
        $this->dict['cron_preset_arr'] = $this->enumToDict(CronEnum::$presetArr);
        return $this;
    }

    public function setTauriPlatformArr(){
        $this->dict['tauri_platform_arr'] = $this->enumToDict(UploadConfigEnum::$tauriPlatformArr);
        return $this;
    }

    public function setNotificationTypeArr(){
        $this->dict['notification_type_arr'] = $this->enumToDict(NotificationEnum::$typeArr);
        return $this;
    }

    public function setNotificationLevelArr(){
        $this->dict['notification_level_arr'] = $this->enumToDict(NotificationEnum::$levelArr);
        return $this;
    }

    public function setNotificationTargetTypeArr(){
        $this->dict['notification_target_type_arr'] = $this->enumToDict(NotificationEnum::$targetTypeArr);
        return $this;
    }

    public function setNotificationReadStatusArr(){
        $this->dict['notification_read_status_arr'] = $this->enumToDict(NotificationEnum::$readStatusArr);
        return $this;
    }

    public function setLogLevelArr(){
        $this->dict['log_level_arr'] = $this->enumToDict(SystemEnum::$logLevelArr);
        return $this;
    }

    public function setLogTailArr(){
        $this->dict['log_tail_arr'] = $this->enumToDict(SystemEnum::$logTailArr);
        return $this;
    }

    public function setAuthPlatformLoginTypeArr(){
        $this->dict['auth_platform_login_type_arr'] = $this->enumToDict(SystemEnum::$loginTypeArr);
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

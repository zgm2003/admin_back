<?php

namespace app\service;

use app\dep\AddressDep;
use app\dep\Ai\AiAgentsDep;
use app\dep\Permission\PermissionDep;
use app\dep\Permission\RoleDep;
use app\dep\System\UploadDriverDep;
use app\dep\System\UploadRuleDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\enum\CronEnum;
use app\enum\GoodsEnum;
use app\enum\NotificationEnum;
use app\enum\PermissionEnum;
use app\enum\SystemEnum;
use app\enum\UploadConfigEnum;
use app\service\Permission\AuthPlatformService;
use support\Cache;

/**
 * 字典服务
 * 负责：为前端提供各类下拉选项、树形结构等字典数据
 * 使用链式调用按需组装，避免一次性加载全部字典
 * 树形数据（权限树、地址树）使用 Redis 永久缓存
 */
class DictService
{
    /** @var array 当前请求组装的字典数据 */
    public array $dict = [];

    // ==================== 缓存管理 ====================

    private const CACHE_KEY_PERMISSION_TREE = 'dict_permission_tree';
    private const CACHE_KEY_ADDRESS_TREE    = 'dict_address_tree';

    /** 清除所有字典缓存 */
    public static function clearCache(): void
    {
        Cache::delete(self::CACHE_KEY_PERMISSION_TREE);
        Cache::delete(self::CACHE_KEY_ADDRESS_TREE);
    }

    /** 清除权限树缓存 */
    public static function clearPermissionCache(): void
    {
        Cache::delete(self::CACHE_KEY_PERMISSION_TREE);
    }

    /** 清除地址树缓存 */
    public static function clearAddressCache(): void
    {
        Cache::delete(self::CACHE_KEY_ADDRESS_TREE);
    }

    // ==================== 枚举类字典（纯内存，无 IO） ====================

    public function setLoginTypeArr(): static
    {
        $this->dict['login_type_arr'] = self::enumToDict(SystemEnum::$loginTypeArr);
        return $this;
    }

    public function setVerifyTypeArr(): static
    {
        $this->dict['verify_type_arr'] = self::enumToDict(SystemEnum::$verifyTypeArr);
        return $this;
    }

    public function setSexArr(): static
    {
        $this->dict['sexArr'] = self::enumToDict(CommonEnum::$sexArr);
        return $this;
    }

    public function setPermissionTypeArr(): static
    {
        $this->dict['permission_type_arr'] = self::enumToDict(PermissionEnum::$typeArr);
        return $this;
    }

    public function setPermissionPlatformArr(): static
    {
        $this->dict['permission_platform_arr'] = self::enumToDict(AuthPlatformService::getPlatformMap());
        return $this;
    }

    public function setCommonStatusArr(): static
    {
        $this->dict['common_status_arr'] = self::enumToDict(CommonEnum::$statusArr);
        return $this;
    }

    public function setUploadImageExtArr(): static
    {
        $this->dict['upload_image_ext_arr'] = self::enumToDict(UploadConfigEnum::$imageExtArr);
        return $this;
    }

    public function setUploadFileExtArr(): static
    {
        $this->dict['upload_file_ext_arr'] = self::enumToDict(UploadConfigEnum::$fileExtArr);
        return $this;
    }

    public function setUploadDriverArr(): static
    {
        $this->dict['upload_driver_arr'] = self::enumToDict(UploadConfigEnum::$driverArr);
        return $this;
    }

    public function setSystemSettingValueTypeArr(): static
    {
        $this->dict['system_setting_value_type_arr'] = self::enumToDict(SystemEnum::$valueTypeArr);
        return $this;
    }

    public function setPlatformArr(): static
    {
        $this->dict['platformArr'] = self::enumToDict(AuthPlatformService::getPlatformMap());
        return $this;
    }

    public function setAiDriverArr(): static
    {
        $this->dict['ai_driver_arr'] = self::enumToDict(AiEnum::$driverArr);
        return $this;
    }

    public function setAiModeArr(): static
    {
        $this->dict['ai_mode_arr'] = self::enumToDict(AiEnum::$modeArr);
        return $this;
    }

    public function setAiSceneArr(): static
    {
        $this->dict['ai_scene_arr'] = self::enumToDict(AiEnum::$sceneArr);
        return $this;
    }

    public function setGoodsPlatformArr(): static
    {
        $this->dict['goods_platform_arr'] = self::enumToDict(GoodsEnum::$platformArr);
        return $this;
    }

    public function setGoodsStatusArr(): static
    {
        $this->dict['goods_status_arr'] = self::enumToDict(GoodsEnum::$statusArr);
        return $this;
    }

    public function setGoodsVoiceArr(): static
    {
        $this->dict['goods_voice_arr'] = self::enumToDict(GoodsEnum::$voiceArr);
        return $this;
    }

    public function setGoodsEmotionArr(): static
    {
        $this->dict['goods_emotion_arr'] = self::enumToDict(GoodsEnum::$emotionArr);
        return $this;
    }

    public function setAiExecutorTypeArr(): static
    {
        $this->dict['ai_executor_type_arr'] = self::enumToDict(AiEnum::$executorTypeArr);
        return $this;
    }

    public function setRunStatusArr(): static
    {
        $this->dict['run_status_arr'] = self::enumToDict(AiEnum::$runStatusArr);
        return $this;
    }

    public function setCronPresetArr(): static
    {
        $this->dict['cron_preset_arr'] = self::enumToDict(CronEnum::$presetArr);
        return $this;
    }

    public function setTauriPlatformArr(): static
    {
        $this->dict['tauri_platform_arr'] = self::enumToDict(UploadConfigEnum::$tauriPlatformArr);
        return $this;
    }

    public function setNotificationTypeArr(): static
    {
        $this->dict['notification_type_arr'] = self::enumToDict(NotificationEnum::$typeArr);
        return $this;
    }

    public function setNotificationLevelArr(): static
    {
        $this->dict['notification_level_arr'] = self::enumToDict(NotificationEnum::$levelArr);
        return $this;
    }

    public function setNotificationTargetTypeArr(): static
    {
        $this->dict['notification_target_type_arr'] = self::enumToDict(NotificationEnum::$targetTypeArr);
        return $this;
    }

    public function setNotificationReadStatusArr(): static
    {
        $this->dict['notification_read_status_arr'] = self::enumToDict(NotificationEnum::$readStatusArr);
        return $this;
    }

    public function setLogLevelArr(): static
    {
        $this->dict['log_level_arr'] = self::enumToDict(SystemEnum::$logLevelArr);
        return $this;
    }

    public function setLogTailArr(): static
    {
        $this->dict['log_tail_arr'] = self::enumToDict(SystemEnum::$logTailArr);
        return $this;
    }

    public function setAuthPlatformLoginTypeArr(): static
    {
        $this->dict['auth_platform_login_type_arr'] = self::enumToDict(SystemEnum::$loginTypeArr);
        return $this;
    }


    // ==================== 数据库查询类字典（有 IO） ====================

    /**
     * 权限树（Redis 永久缓存，权限变更时由 PermissionModule 清除）
     * label 带平台标识前缀，保留 platform 字段供前端过滤
     */
    public function setPermissionTree(): static
    {
        $cached = Cache::get(self::CACHE_KEY_PERMISSION_TREE);
        if ($cached !== null) {
            $this->dict['permission_tree'] = $cached;
            return $this;
        }

        $allPermissions = (new PermissionDep())->getAllPermissions();
        $platformMap = AuthPlatformService::getPlatformMap();

        $resCategory = array_map(fn($item) => [
            'id'        => $item['id'],
            'label'     => ($item['platform'] ? '[' . ($platformMap[$item['platform']] ?? $item['platform']) . '] ' : '') . $item['name'],
            'value'     => $item['id'],
            'parent_id' => $item['parent_id'],
            'platform'  => $item['platform'] ?? '',
        ], $allPermissions);

        $tree = listToTree($resCategory, -1);
        Cache::set(self::CACHE_KEY_PERMISSION_TREE, $tree);
        $this->dict['permission_tree'] = $tree;

        return $this;
    }

    /**
     * 地址树（Redis 永久缓存，地址数据变更时手动清除）
     */
    public function setAuthAdressTree(): static
    {
        $cached = Cache::get(self::CACHE_KEY_ADDRESS_TREE);
        if ($cached !== null) {
            $this->dict['auth_address_tree'] = $cached;
            return $this;
        }

        $allAddressMap = (new AddressDep())->getAllMap();

        $resCategory = array_map(fn($item) => [
            'id'        => $item['id'],
            'label'     => $item['name'],
            'value'     => $item['id'],
            'parent_id' => $item['parent_id'],
        ], $allAddressMap);

        $tree = listToTree($resCategory, -1);
        Cache::set(self::CACHE_KEY_ADDRESS_TREE, $tree);
        $this->dict['auth_address_tree'] = $tree;

        return $this;
    }

    /**
     * 角色列表（数据量小，直接查询无需缓存）
     */
    public function setRoleArr(): static
    {
        $res = (new RoleDep())->allActive();
        $this->dict['roleArr'] = $res->map(fn($item) => [
            'value' => $item->id,
            'label' => $item->name,
        ]);

        return $this;
    }

    /**
     * 上传驱动列表（driver + bucket 组合展示）
     */
    public function setUploadDriverList(): static
    {
        $this->dict['upload_driver_list'] = (new UploadDriverDep())->getDict()->map(fn($item) => [
            'label' => "{$item->driver} - {$item->bucket}",
            'value' => $item->id,
        ]);

        return $this;
    }

    /**
     * 上传规则列表
     */
    public function setUploadRuleList(): static
    {
        $this->dict['upload_rule_list'] = (new UploadRuleDep())->getDict()->map(fn($item) => [
            'label' => $item->title,
            'value' => $item->id,
        ]);

        return $this;
    }

    /**
     * AI Agent 列表
     */
    public function setAgentArr(): static
    {
        $res = (new AiAgentsDep())->allActive();
        $this->dict['agentArr'] = $res->map(fn($item) => [
            'value' => $item->id,
            'label' => $item->name,
        ]);

        return $this;
    }

    // ==================== 输出 ====================

    /**
     * 获取已组装的字典数据
     */
    public function getDict(): array
    {
        return $this->dict;
    }

    // ==================== 工具方法 ====================

    /**
     * 枚举数组 → 前端字典格式 [{label, value}]
     */
    public static function enumToDict(array $enum): array
    {
        $res = [];
        foreach ($enum as $value => $label) {
            $res[] = ['label' => $label, 'value' => $value];
        }

        return $res;
    }
}
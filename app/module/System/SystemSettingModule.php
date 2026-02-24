<?php

namespace app\module\System;

use app\dep\System\SystemSettingDep;
use app\enum\CommonEnum;
use app\enum\SystemEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\System\SettingService;
use app\validate\System\SystemSettingValidate;

/**
 * 系统配置模块
 * 负责：键值对形式的系统配置项 CRUD + 状态切换（带缓存自动清理）
 */
class SystemSettingModule extends BaseModule
{
    /**
     * 初始化（返回配置值类型字典）
     */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setSystemSettingValueTypeArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 配置列表（分页，支持按 key 前缀和状态过滤）
     */
    public function list($request): array
    {
        $param = $this->validate($request, SystemSettingValidate::list());
        $res = $this->dep(SystemSettingDep::class)->list($param);

        $list = $res->map(fn($it) => [
            'id'              => $it['id'],
            'setting_key'     => $it['setting_key'],
            'setting_value'   => $it['setting_value'],
            'value_type'      => $it['value_type'],
            'value_type_name' => SystemEnum::$valueTypeArr[$it['value_type']],
            'remark'          => $it['remark'],
            'status'          => $it['status'],
            'status_name'     => CommonEnum::$statusArr[$it['status']],
            'is_del'          => $it['is_del'],
            'created_at'      => $it['created_at'],
            'updated_at'      => $it['updated_at'],
        ]);

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 新增配置
     * 流程：校验入参 → 值类型合法性校验 → 通过 SettingService 写入（自动类型转换+缓存管理）
     */
    public function add($request): array
    {
        $param = $this->validate($request, SystemSettingValidate::add());

        // 按值类型校验合法性
        $this->validateValueType((int)$param['type'], $param['value'] ?? '');

        SettingService::set($param['key'], $param['value'], (int)$param['type'], $param['remark'] ?? '');

        return self::success();
    }

    /**
     * 编辑配置
     * 流程：校验入参 → 值类型合法性校验 → 按 ID 更新（Dep 层自动清理缓存）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, SystemSettingValidate::edit());

        // 按值类型校验合法性
        $this->validateValueType((int)$param['type'], $param['value'] ?? '');

        // JSON 类型：非字符串值需 encode 后存储
        $settingValue = (int)$param['type'] === 4
            ? (is_string($param['value']) ? $param['value'] : json_encode($param['value']))
            : (string)$param['value'];

        $ok = $this->dep(SystemSettingDep::class)->updateById((int)$param['id'], [
            'setting_value' => $settingValue,
            'value_type'    => (int)$param['type'],
            'remark'        => $param['remark'] ?? '',
        ]);
        self::throwIf(!$ok, '配置不存在');

        return self::success();
    }

    /**
     * 删除配置（软删，支持批量，Dep 层自动清理缓存）
     */
    public function del($request): array
    {
        $param = $this->validate($request, SystemSettingValidate::del());
        $this->dep(SystemSettingDep::class)->deleteById($param['id']);
        return self::success();
    }

    /**
     * 切换配置状态（启用/禁用，Dep 层自动清理缓存）
     */
    public function status($request): array
    {
        $param = $this->validate($request, SystemSettingValidate::status());
        $ok = $this->dep(SystemSettingDep::class)->setStatusById((int)$param['id'], (int)$param['status']);
        self::throwIf(!$ok, '配置项不存在');
        return self::success();
    }

    // ==================== 私有方法 ====================

    /**
     * 按值类型校验合法性
     * type: 1=字符串 2=数值 3=布尔 4=JSON
     */
    private function validateValueType(int $type, mixed $value): void
    {
        self::throwIf($type === 2 && !is_numeric($value), '数值类型需为数字');
        self::throwIf(
            $type === 3 && !in_array(strtolower((string)$value), ['0', '1', 'true', 'false'], true),
            '布尔类型需为 true/false 或 0/1'
        );
        if ($type === 4) {
            $j = json_decode((string)$value, true);
            self::throwIf(!is_array($j), 'JSON 类型需为合法 JSON');
        }
    }
}

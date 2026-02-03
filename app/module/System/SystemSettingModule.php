<?php

namespace app\module\System;

use app\dep\System\SystemSettingDep;
use app\enum\CommonEnum;
use app\enum\SystemEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\System\SettingService;
use app\validate\System\SystemSettingValidate;

class SystemSettingModule extends BaseModule
{
    protected SystemSettingDep $systemSettingDep;
    protected DictService $dictService;

    public function __construct()
    {
        $this->systemSettingDep = $this->dep(SystemSettingDep::class);
        $this->dictService = $this->svc(DictService::class);
    }

    public function init($request)
    {
        $data['dict'] = $this->dictService
            ->setSystemSettingValueTypeArr()
            ->getDict();
        return self::success($data);
    }

    public function list($request)
    {
        $param = $this->validate($request, SystemSettingValidate::list());
        $res = $this->systemSettingDep->list($param);
        $list = $res->map(function ($it) {
            return [
                'id' => $it['id'],
                'setting_key' => $it['setting_key'],
                'setting_value' => $it['setting_value'],
                'value_type' => $it['value_type'],
                'value_type_name' => SystemEnum::$valueTypeArr[$it['value_type']],
                'remark' => $it['remark'],
                'status' => $it['status'],
                'status_name' => CommonEnum::$statusArr[$it['status']],
                'is_del' => $it['is_del'],
                'created_at' => $it['created_at']->toDateTimeString(),
                'updated_at' => $it['updated_at']->toDateTimeString(),
            ];
        });
        $page = [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        return self::paginate($list, $page);
    }

    public function add($request)
    {
        $param = $this->validate($request, SystemSettingValidate::add());
        
        self::throwIf((int)$param['type'] === 2 && !is_numeric($param['value']), '数值类型需为数字');
        self::throwIf((int)$param['type'] === 3 && !in_array(strtolower((string)$param['value']), ['0', '1', 'true', 'false'], true), '布尔类型需为 true/false 或 0/1');
        if ((int)$param['type'] === 4) {
            $j = json_decode((string)$param['value'], true);
            self::throwIf(!is_array($j), 'JSON 类型需为合法 JSON');
        }
        SettingService::set($param['key'], $param['value'], (int)$param['type'], $param['remark'] ?? '');
        return self::success();
    }

    public function edit($request)
    {
        $param = $this->validate($request, SystemSettingValidate::edit());
        
        self::throwIf((int)$param['type'] === 2 && !is_numeric($param['value']), '数值类型需为数字');
        self::throwIf((int)$param['type'] === 3 && !in_array(strtolower((string)$param['value']), ['0', '1', 'true', 'false'], true), '布尔类型需为 true/false 或 0/1');
        if ((int)$param['type'] === 4) {
            $j = json_decode((string)$param['value'], true);
            self::throwIf(!is_array($j), 'JSON 类型需为合法 JSON');
        }
        $ok = $this->systemSettingDep->updateById((int)$param['id'], [
            'setting_value' => (int)$param['type'] === 4 ? (is_string($param['value']) ? $param['value'] : json_encode($param['value'])) : (string)$param['value'],
            'value_type' => (int)$param['type'],
            'remark' => $param['remark'] ?? '',
        ]);
        self::throwIf(!$ok, '配置不存在');
        return self::success();
    }

    public function del($request)
    {
        $param = $this->validate($request, SystemSettingValidate::del());
        $this->systemSettingDep->deleteById($param['id']);
        return self::success();
    }

    public function status($request)
    {
        $param = $this->validate($request, SystemSettingValidate::status());
        $ok = $this->systemSettingDep->setStatusById((int)$param['id'], (int)$param['status']);
        self::throwIf(!$ok, '配置不存在');
        return self::success();
    }

    // 保留 clearCache 可选方法（可不暴露路由）
}

<?php

namespace app\module\System;

use app\dep\System\SystemSettingDep;
use app\enum\CommonEnum;
use app\enum\SystemEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\System\SystemSettingValidate;

class SystemSettingModule extends BaseModule
{
    public $dep;

    public function __construct()
    {
        $this->dep = new SystemSettingDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setSystemSettingValueTypeArr()
            ->getDict();
        return self::success($data);
    }

    public function list($request)
    {
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;
        $res = $this->dep->list($param);
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
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        return self::paginate($list, $page);
    }

    public function add($request)
    {
        try { $param = $this->validate($request, SystemSettingValidate::add()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        if ((int)$param['type'] === 2 && !is_numeric($param['value'])) return self::error('数值类型需为数字');
        if ((int)$param['type'] === 3 && !in_array(strtolower((string)$param['value']), ['0','1','true','false'], true)) return self::error('布尔类型需为 true/false 或 0/1');
        if ((int)$param['type'] === 4) { $j = json_decode((string)$param['value'], true); if (!is_array($j)) return self::error('JSON 类型需为合法 JSON'); }
        $this->dep->setValue($param['key'], $param['value'], (int)$param['type'], $param['remark'] ?? '');
        return self::success();
    }

    public function edit($request)
    {
        try { $param = $this->validate($request, SystemSettingValidate::edit()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        if ((int)$param['type'] === 2 && !is_numeric($param['value'])) return self::error('数值类型需为数字');
        if ((int)$param['type'] === 3 && !in_array(strtolower((string)$param['value']), ['0','1','true','false'], true)) return self::error('布尔类型需为 true/false 或 0/1');
        if ((int)$param['type'] === 4) { $j = json_decode((string)$param['value'], true); if (!is_array($j)) return self::error('JSON 类型需为合法 JSON'); }
        $ok = $this->dep->editById((int)$param['id'], [
            'setting_value' => (int)$param['type'] === 4 ? (is_string($param['value']) ? $param['value'] : json_encode($param['value'])) : (string)$param['value'],
            'value_type' => (int)$param['type'],
            'remark' => $param['remark'] ?? '',
        ]);
        if (!$ok) return self::error('配置不存在');
        return self::success();
    }

    public function del($request)
    {
        try { $param = $this->validate($request, SystemSettingValidate::del()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $this->dep->delById($param['id']);
        return self::success();
    }

    public function status($request)
    {
        try { $param = $this->validate($request, SystemSettingValidate::status()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $ok = $this->dep->setStatusById((int)$param['id'], (int)$param['status']);
        if (!$ok) return self::error('配置不存在');
        return self::success();
    }

    // 保留 clearCache 可选方法（可不暴露路由）
}

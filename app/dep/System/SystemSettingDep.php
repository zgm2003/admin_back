<?php

namespace app\dep\System;

use app\model\System\SystemSettingModel;
use app\enum\CommonEnum;
use support\Cache;

class SystemSettingDep
{
    public $model;

    public function __construct()
    {
        $this->model = new SystemSettingModel();
    }

    public function firstByKey(string $key)
    {
        return $this->model
            ->where('setting_key', $key)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    public function getValue(string $key, $default = null)
    {
        $cacheKey = 'sys_setting:' . $key;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;
        $row = $this->model
            ->where('setting_key', $key)
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->first();
        if (!$row) return $default;
        $val = $row->setting_value;
        switch ((int)$row->value_type) {
            case 2:
                $val = is_numeric($val) ? $val + 0 : $default;
                break;
            case 3:
                $val = in_array(strtolower((string)$val), ['1', 'true'], true);
                break;
            case 4:
                $decoded = json_decode((string)$val, true);
                $val = is_array($decoded) ? $decoded : $default;
                break;
            default:
                $val = (string)$val;
        }
        Cache::set($cacheKey, $val, 86400);
        return $val;
    }

    public function setValue(string $key, $value, int $type = 1, string $remark = '')
    {
        $exists = $this->firstByKey($key);
        $data = [
            'setting_key' => $key,
            'setting_value' => $type === 4 ? (is_string($value) ? $value : json_encode($value)) : (string)$value,
            'value_type' => $type,
            'remark' => $remark,
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ];
        if ($exists) {
            $this->model->where('id', $exists->id)->update($data);
        } else {
            $this->model->insertGetId($data);
        }
        Cache::delete('sys_setting:' . $key);
        return true;
    }

    public function list(array $param)
    {
        $pageSize = $param['page_size'];
        $currentPage = $param['current_page'];
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['key']), function ($q) use ($param) {
                $q->where('setting_key', 'like', '%' . $param['key'] . '%');
            })
            ->when(isset($param['status']) && $param['status'] !== '', function ($q) use ($param) {
                $q->where('status', (int)$param['status']);
            })
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }

    public function delByKey(string $key)
    {
        $this->model
            ->where('setting_key', $key)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
        Cache::delete('sys_setting:' . $key);
    }

    public function setStatusByKey(string $key, int $status)
    {
        $this->model
            ->where('setting_key', $key)
            ->where('is_del', CommonEnum::NO)
            ->update(['status' => $status]);
        Cache::delete('sys_setting:' . $key);
    }

    public function editById(int $id, array $data)
    {
        $row = $this->model->where('id', $id)->where('is_del', CommonEnum::NO)->first();
        if (!$row) return false;
        $this->model->where('id', $id)->update($data);
        Cache::delete('sys_setting:' . $row->setting_key);
        return true;
    }
    public function delById($id)
    {
        $ids = is_array($id) ? $id : [$id];
        $rows = $this->model->whereIn('id', $ids)->get(['setting_key'])->toArray();
        $this->model->whereIn('id', $ids)->update(['is_del' => CommonEnum::YES]);
        foreach ($rows as $r) {
            if (!empty($r['setting_key'])) Cache::delete('sys_setting:' . $r['setting_key']);
        }
        return true;
    }
    public function setStatusById(int $id, int $status)
    {
        $row = $this->model->where('id', $id)->where('is_del', CommonEnum::NO)->first();
        if (!$row) return false;
        $this->model->where('id', $id)->update(['status' => $status]);
        Cache::delete('sys_setting:' . $row->setting_key);
        return true;
    }
}


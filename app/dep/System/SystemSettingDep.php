<?php

namespace app\dep\System;

use app\dep\BaseDep;
use app\model\System\SystemSettingModel;
use app\enum\CommonEnum;
use support\Cache;
use support\Model;

class SystemSettingDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new SystemSettingModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据 key 查询
     */
    public function findByKey(string $key)
    {
        return $this->model
            ->where('setting_key', $key)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 根据 key 查询原始记录（带缓存）
     */
    public function getRaw(string $key): ?array
    {
        $cacheKey = 'sys_setting_raw_' . str_replace('.', '_', $key);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        $row = $this->model
            ->where('setting_key', $key)
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->first();

        if (!$row) {
            Cache::set($cacheKey, false, 86400); // 缓存空结果
            return null;
        }

        $data = [
            'setting_value' => $row->setting_value,
            'value_type' => (int)$row->value_type,
        ];
        Cache::set($cacheKey, $data, 86400);
        return $data;
    }

    /**
     * 原始写入（不做类型转换）
     */
    public function setRaw(string $key, string $value, int $type = 1, string $remark = ''): bool
    {
        $exists = $this->findByKey($key);
        $data = [
            'setting_key' => $key,
            'setting_value' => $value,
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

        Cache::delete('sys_setting_raw_' . str_replace('.', '_', $key));
        return true;
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['key']), fn($q) => $q->where('setting_key', 'like', $param['key'] . '%'))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    // ==================== 写入方法 ====================

    /**
     * 根据 key 删除（软删）
     */
    public function deleteByKey(string $key): void
    {
        $this->model
            ->where('setting_key', $key)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
        Cache::delete('sys_setting_raw_' . str_replace('.', '_', $key));
    }

    /**
     * 根据 key 设置状态
     */
    public function setStatusByKey(string $key, int $status): void
    {
        $this->model
            ->where('setting_key', $key)
            ->where('is_del', CommonEnum::NO)
            ->update(['status' => $status]);
        Cache::delete('sys_setting_raw_' . str_replace('.', '_', $key));
    }

    /**
     * 根据 ID 更新（带缓存清理）
     */
    public function updateById(int $id, array $data): bool
    {
        $row = $this->model->where('id', $id)->where('is_del', CommonEnum::NO)->first();
        if (!$row) {
            return false;
        }
        $this->model->where('id', $id)->update($data);
        Cache::delete('sys_setting_raw_' . str_replace('.', '_', $row->setting_key));
        return true;
    }

    /**
     * 根据 ID 删除（带缓存清理）
     */
    public function deleteById($ids): bool
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $rows = $this->model->whereIn('id', $ids)->get(['setting_key'])->toArray();
        $this->model->whereIn('id', $ids)->update(['is_del' => CommonEnum::YES]);
        foreach ($rows as $r) {
            if (!empty($r['setting_key'])) {
                Cache::delete('sys_setting_raw_' . str_replace('.', '_', $r['setting_key']));
            }
        }
        return true;
    }

    /**
     * 根据 ID 设置状态（带缓存清理）
     */
    public function setStatusById(int $id, int $status): bool
    {
        $row = $this->model->where('id', $id)->where('is_del', CommonEnum::NO)->first();
        if (!$row) {
            return false;
        }
        $this->model->where('id', $id)->update(['status' => $status]);
        Cache::delete('sys_setting_raw_' . str_replace('.', '_', $row->setting_key));
        return true;
    }
}

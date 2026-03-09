<?php

namespace app\dep\System;

use app\dep\BaseDep;
use app\model\System\TauriVersionModel;
use app\enum\CommonEnum;
use support\Model;

/**
 * Tauri 版本管理数据层
 */
class TauriVersionDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new TauriVersionModel();
    }

    /**
     * 获取版本列表
     */
    public function list(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['platform']), fn($q) => $q->where('platform', $param['platform']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 获取最新版本
     */
    public function getLatest(string $platform = 'windows-x86_64')
    {
        return $this->model->where('platform', $platform)->where('is_del', CommonEnum::NO)->where('is_latest', CommonEnum::YES)->first();
    }

    /**
     * 设置为最新版本
     */
    public function setLatest(int $id, string $platform): int
    {
        $this->model->where('platform', $platform)->where('is_del', CommonEnum::NO)->update(['is_latest' => CommonEnum::NO]);
        return $this->model->where('id', $id)->update(['is_latest' => CommonEnum::YES]);
    }

    /**
     * 真删除版本（这个表不用软删除）
     */
    /**
     * 根据条件获取版本记录
     */
    public function getByCondition(array $condition)
    {
        $query = $this->model->where('is_del', CommonEnum::NO);
        foreach ($condition as $field => $value) {
            $query = $query->where($field, $value);
        }
        return $query->first();
    }

    /**
     * 检查版本+平台是否已存在（排除指定 ID）
     */
    public function existsByVersionPlatform(string $version, string $platform, ?int $excludeId = null): bool
    {
        return $this->model
            ->where('version', $version)
            ->where('platform', $platform)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}

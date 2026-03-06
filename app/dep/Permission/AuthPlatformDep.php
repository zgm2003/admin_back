<?php

namespace app\dep\Permission;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Permission\AuthPlatformModel;
use app\service\Permission\AuthPlatformService;
use support\Cache;
use support\Model;

class AuthPlatformDep extends BaseDep
{
    const CACHE_PREFIX = 'auth_platform_';
    const CACHE_ALL    = 'auth_platform_all';

    protected function createModel(): Model
    {
        return new AuthPlatformModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据 code 获取启用的平台配置（永久缓存，写时清除）
     */
    public function getByCode(string $code): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $code;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        $row = $this->model
            ->where('code', $code)
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->first();

        if (!$row) {
            Cache::set($cacheKey, false);
            return null;
        }

        $data = $row->toArray();
        Cache::set($cacheKey, $data);
        return $data;
    }

    /**
     * 获取所有启用的平台 code 列表（永久缓存）
     */
    public function getAllActiveCodes(): array
    {
        $cached = Cache::get(self::CACHE_ALL);
        if ($cached !== null) {
            return $cached;
        }

        $codes = $this->model
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->pluck('code')
            ->toArray();

        Cache::set(self::CACHE_ALL, $codes);
        return $codes;
    }

    /**
     * 获取所有启用的平台 code→name 映射（永久缓存）
     */
    public function getAllActiveMap(): array
    {
        $cacheKey = self::CACHE_ALL . '_map';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $map = $this->model
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->pluck('name', 'code')
            ->toArray();

        Cache::set($cacheKey, $map);
        return $map;
    }


    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['name']), fn($q) => $q->where('name', 'like', '%' . $param['name'] . '%'))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->orderBy('id', 'asc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 检查 code 是否已存在（排除指定 ID）
     */
    public function existsByCode(string $code, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where('code', $code)
            ->where('is_del', CommonEnum::NO);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    // ==================== 写入方法（均主动清缓存） ====================

    public function addPlatform(array $data): int
    {
        $id = $this->model->insertGetId($data);
        $this->clearCache($data['code'] ?? '');
        return $id;
    }

    public function updateById(int $id, array $data, ?string $oldCode = null): bool
    {
        $exists = $this->model->where('id', $id)->where('is_del', CommonEnum::NO)->exists();
        if (!$exists) {
            return false;
        }
        $this->model->where('id', $id)->where('is_del', CommonEnum::NO)->update($data);
        if ($oldCode) {
            $this->clearCache($oldCode);
        }
        if (!empty($data['code'])) {
            $this->clearCache($data['code']);
        }
        return true;
    }

    public function deleteByIds($ids): bool
    {
        $ids = \is_array($ids) ? $ids : [$ids];
        $rows = $this->model->whereIn('id', $ids)->where('is_del', CommonEnum::NO)->get(['code']);
        $count = $this->model->whereIn('id', $ids)->update(['is_del' => CommonEnum::YES]);
        if ($count > 0) {
            foreach ($rows as $r) {
                $this->clearCache($r->code);
            }
        }
        return $count > 0;
    }

    public function setStatusById(int $id, int $status): bool
    {
        $row = $this->model->where('id', $id)->where('is_del', CommonEnum::NO)->first(['code']);
        if (!$row) {
            return false;
        }
        $this->model->where('id', $id)->update(['status' => $status]);
        $this->clearCache($row->code);
        return true;
    }

    // ==================== 缓存管理 ====================

    private function clearCache(string $code = ''): void
    {
        // 清 Redis 缓存
        Cache::delete(self::CACHE_ALL);
        Cache::delete(self::CACHE_ALL . '_map');
        if ($code) {
            Cache::delete(self::CACHE_PREFIX . $code);
        }
        // 清当前进程内存缓存
        AuthPlatformService::flushMemCache();
    }
}

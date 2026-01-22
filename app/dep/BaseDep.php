<?php

namespace app\dep;

use app\enum\CommonEnum;
use support\Model;
use RuntimeException;

/**
 * Dep 基类
 * 封装通用的 CRUD 操作，类似 MyBatis-Plus 的 BaseMapper
 * 
 * 命名规范：
 * - find*: 不检查 is_del，用于审计、关联查询
 * - get*: 检查 is_del=NO，用于正常业务查询
 */
abstract class BaseDep
{
    protected Model $model;

    /**
     * 子类需要实现此方法返回模型实例
     */
    abstract protected function createModel(): Model;

    public function __construct()
    {
        $this->model = $this->createModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据 ID 查询（不检查 is_del，用于审计、关联查询）
     */
    public function find(int $id)
    {
        return $this->model->where('id', $id)->first();
    }

    /**
     * 根据 ID 查询，找不到抛异常
     */
    public function findOrFail(int $id)
    {
        $result = $this->find($id);
        if (!$result) {
            throw new RuntimeException('记录不存在: ' . $id);
        }
        return $result;
    }

    /**
     * 根据 ID 查询（检查 is_del=NO，用于正常业务）
     */
    public function get(int $id)
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 批量查询，返回 id => model 的 Collection
     * 不检查 is_del，用于关联查询（如显示已删除的关联数据）
     */
    public function getMap(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }
        return $this->model
            ->whereIn('id', array_unique($ids))
            ->get()
            ->keyBy('id');
    }

    /**
     * 批量查询（只查未删除的）
     */
    public function getMapActive(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }
        return $this->model
            ->whereIn('id', array_unique($ids))
            ->where('is_del', CommonEnum::NO)
            ->get()
            ->keyBy('id');
    }

    // ==================== 写入方法 ====================

    /**
     * 新增记录，返回 ID
     */
    public function add(array $data): int
    {
        return $this->model->insertGetId($data);
    }

    /**
     * 更新记录（支持单个或批量）
     */
    public function update($ids, array $data): int
    {
        $ids = is_array($ids) ? $ids : [$ids];
        return $this->model->whereIn('id', $ids)->update($data);
    }

    /**
     * 软删除（更新 is_del 字段）
     */
    public function delete($ids): int
    {
        $ids = is_array($ids) ? $ids : [$ids];
        return $this->model
            ->whereIn('id', $ids)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }

    /**
     * 批量设置状态
     */
    public function setStatus($ids, int $status): int
    {
        $ids = is_array($ids) ? $ids : [$ids];
        return $this->model
            ->whereIn('id', $ids)
            ->where('is_del', CommonEnum::NO)
            ->update(['status' => $status]);
    }

    // ==================== 游标分页（深分页优化） ====================

    /**
     * 游标分页查询
     * 
     * 使用 WHERE id < cursor 代替 OFFSET，性能恒定 O(1)
     * 适用于日志类、流水类表，无需跳页的场景
     * 
     * @param array $param 必须包含 page_size，可选 cursor（上一页最后一条的 id）
     * @param callable|null $queryBuilder 额外的查询条件，如 fn($q) => $q->where('user_id', 1)
     * @param array $columns 查询的字段
     * @param bool $checkDel 是否检查 is_del 字段（无 is_del 的表传 false）
     * @return array ['list' => Collection, 'next_cursor' => int|null, 'has_more' => bool]
     */
    public function listCursor(array $param, ?callable $queryBuilder = null, array $columns = ['*'], bool $checkDel = true): array
    {
        $pageSize = $param['page_size'] ?? 20;
        $cursor = $param['cursor'] ?? null;
        
        $query = $this->model->select($columns);
        
        // 有 is_del 字段的表自动加条件
        if ($checkDel) {
            $query->where('is_del', CommonEnum::NO);
        }
        
        // 游标条件：id < cursor
        if ($cursor) {
            $query->where('id', '<', $cursor);
        }
        
        // 额外条件
        if ($queryBuilder) {
            $queryBuilder($query);
        }
        
        // 多取一条判断 has_more
        $list = $query->orderBy('id', 'desc')->limit($pageSize + 1)->get();
        
        $hasMore = $list->count() > $pageSize;
        if ($hasMore) {
            $list = $list->take($pageSize);
        }
        
        return [
            'list' => $list,
            'next_cursor' => $hasMore ? $list->last()->id : null,
            'has_more' => $hasMore
        ];
    }

    // ==================== 辅助方法 ====================

    /**
     * 获取新的查询构建器（供子类扩展使用）
     */
    protected function query()
    {
        return $this->model->newQuery();
    }

    /**
     * 检查记录是否存在
     */
    public function exists(int $id): bool
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->exists();
    }
}

<?php

namespace app\dep;

use app\enum\CommonEnum;
use support\Model;
use RuntimeException;
use InvalidArgumentException;

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

    protected function primaryKey(): string
    {
        return $this->model->getKeyName();
    }

    protected function normalizeIds($ids): array
    {
        $ids = is_array($ids) ? $ids : [$ids];

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn(int $id) => $id > 0
        )));
    }

    // ==================== 查询方法 ====================

    /**
     * 根据 ID 查询（不检查 is_del，用于审计、关联查询）
     */
    public function find(int $id)
    {
        return $this->model->where($this->primaryKey(), $id)->first();
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
            ->where($this->primaryKey(), $id)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 根据 ID 查询（检查 is_del=NO），找不到抛异常
     */
    public function getOrFail(int $id)
    {
        $result = $this->get($id);
        if (!$result) {
            throw new RuntimeException('记录不存在: ' . $id);
        }
        return $result;
    }

    /**
     * 批量查询，返回 id => model 的 Collection
     * 不检查 is_del，用于关联查询（如显示已删除的关联数据）
     * @param array $columns 查询字段，默认 ['*']，建议指定避免拉取大字段
     */
    public function getMap(array $ids, array $columns = ['*'])
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return collect();
        }

        $key = $this->primaryKey();

        return $this->model
            ->select($columns)
            ->whereIn($key, $ids)
            ->get()
            ->keyBy($key);
    }

    /**
     * 批量查询（只查未删除的）
     * @param array $columns 查询字段，默认 ['*']，建议指定避免拉取大字段
     */
    public function getMapActive(array $ids, array $columns = ['*'])
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return collect();
        }

        $key = $this->primaryKey();

        return $this->model
            ->select($columns)
            ->whereIn($key, $ids)
            ->where('is_del', CommonEnum::NO)
            ->get()
            ->keyBy($key);
    }

    // ==================== 写入方法 ====================

    /**
     * 新增记录，返回 ID
     */
    public function add(array $data): int
    {
        $key = $this->primaryKey();
        if (array_key_exists($key, $data)) {
            $this->model->insert($data);
            return (int) $data[$key];
        }

        return (int) $this->model->insertGetId($data);
    }

    /**
     * 更新记录（支持单个或批量）
     */
    public function update($ids, array $data): int
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return 0;
        }

        return $this->model->whereIn($this->primaryKey(), $ids)->update($data);
    }

    /**
     * 软删除（更新 is_del 字段）
     */
    public function delete($ids): int
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return 0;
        }

        return $this->model
            ->whereIn($this->primaryKey(), $ids)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }

    /**
     * 批量设置状态
     */
    public function setStatus($ids, int $status): int
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return 0;
        }

        return $this->model
            ->whereIn($this->primaryKey(), $ids)
            ->where('is_del', CommonEnum::NO)
            ->update(['status' => $status]);
    }

    /**
     * 原子递减指定字段（最小值为 0，防止负数）
     */
    public function decrement(int $id, string $column, int $amount = 1): int
    {
        $column = $this->validateCounterColumn($column);
        $amount = $this->validateCounterAmount($amount);

        return $this->model
            ->where($this->primaryKey(), $id)
            ->where($column, '>=', $amount)
            ->decrement($column, $amount);
    }

    /**
     * 原子递增指定字段
     */
    public function increment(int $id, string $column, int $amount = 1): int
    {
        $column = $this->validateCounterColumn($column);
        $amount = $this->validateCounterAmount($amount);

        return $this->model
            ->where($this->primaryKey(), $id)
            ->increment($column, $amount);
    }

    protected function validateCounterColumn(string $column): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
            throw new InvalidArgumentException('Invalid counter column.');
        }

        return $column;
    }

    protected function validateCounterAmount(int $amount): int
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Counter amount must be greater than 0.');
        }

        return $amount;
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
            ->where($this->primaryKey(), $id)
            ->where('is_del', CommonEnum::NO)
            ->exists();
    }
}

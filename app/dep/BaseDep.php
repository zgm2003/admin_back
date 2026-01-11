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

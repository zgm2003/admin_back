<?php

namespace app\dep\Ai;

use app\model\Ai\AiModel;
use app\enum\CommonEnum;

class AiModelsDep
{
    protected AiModel $model;

    public function __construct()
    {
        $this->model = new AiModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        $pageSize = $param['page_size'] ?? 20;
        $currentPage = $param['current_page'] ?? 1;

        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['driver']), function ($q) use ($param) {
                $q->where('driver', $param['driver']);
            })
            ->when(isset($param['status']) && $param['status'] !== '', function ($q) use ($param) {
                $q->where('status', (int)$param['status']);
            })
            ->when(!empty($param['name']), function ($q) use ($param) {
                $q->where('name', 'like', '%' . $param['name'] . '%');
            })
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }

    /**
     * 根据 ID 获取单条记录
     */
    public function getById(int $id)
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 创建记录
     */
    public function create(array $data): int
    {
        return $this->model->insertGetId($data);
    }

    /**
     * 根据 ID 更新
     */
    public function updateById(int $id, array $data): bool
    {
        $row = $this->getById($id);
        if (!$row) {
            return false;
        }

        $this->model->where('id', $id)->update($data);
        return true;
    }

    /**
     * 软删除（支持单个或数组）
     */
    public function softDelete($ids): int
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

    /**
     * 检查 driver + name 唯一性
     */
    public function existsByDriverAndName(string $driver, string $name, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where('driver', $driver)
            ->where('name', $name)
            ->where('is_del', CommonEnum::NO);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 批量查询，返回 id => model 的 Collection
     */
    public function getMapByIds(array $ids)
    {
        if (empty($ids)) return collect();
        return $this->model
            ->whereIn('id', array_unique($ids))
            ->where('is_del', CommonEnum::NO)
            ->get()
            ->keyBy('id');
    }
}

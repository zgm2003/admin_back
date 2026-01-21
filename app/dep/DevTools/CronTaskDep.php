<?php

namespace app\dep\DevTools;

use app\dep\BaseDep;
use app\model\DevTools\CronTaskModel;
use app\enum\CommonEnum;
use support\Model;

class CronTaskDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new CronTaskModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据 name 查询任务
     */
    public function findByName(string $name)
    {
        return $this->model
            ->select(['id', 'name', 'title', 'cron', 'handler', 'status'])
            ->where('name', $name)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 检查 name 是否已存在（排除指定 ID）
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        return $this->model
            ->where('name', $name)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * 获取所有启用的任务
     */
    public function getEnabled()
    {
        return $this->model
            ->select(['id', 'name', 'title', 'cron', 'handler'])
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->get();
    }

    /**
     * 检查任务是否启用
     */
    public function isEnabled(string $name): bool
    {
        return $this->model
            ->where('name', $name)
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->exists();
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页）
     */
    public function list(array $param)
    {
        $columns = ['id', 'name', 'title', 'description', 'cron', 'cron_readable', 'handler', 'status', 'created_at', 'updated_at'];
        return $this->model
            ->select($columns)
            ->when(!empty($param['title']), fn($q) => $q->where('title', 'like', '%' . $param['title'] . '%'))
            ->when(!empty($param['status']), fn($q) => $q->where('status', $param['status']))
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'asc')
            ->paginate($param['page_size'] ?? 20, $columns, 'page', $param['current_page'] ?? 1);
    }

    // ==================== 写入方法 ====================

    /**
     * 切换状态
     */
    public function toggleStatus(int $id, int $status): int
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->update(['status' => $status]);
    }
}

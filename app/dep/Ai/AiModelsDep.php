<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiModelModel;
use app\enum\CommonEnum;
use support\Model;

class AiModelsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiModelModel();
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
            ->when(!empty($param['driver']), fn($q) => $q->where('driver', $param['driver']))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->when(!empty($param['name']), fn($q) => $q->where('name', 'like', $param['name'] . '%'))
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
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
}

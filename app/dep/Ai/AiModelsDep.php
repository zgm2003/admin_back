<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiModelsModel;
use app\enum\CommonEnum;
use support\Model;

class AiModelsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiModelsModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        $columns = [
            'id', 'name', 'driver', 'model_code', 'endpoint',
            'api_key_hint', 'modalities', 'status', 'created_at', 'updated_at',
        ];

        return $this->model
            ->select($columns)
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['driver']), fn($q) => $q->where('driver', $param['driver']))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->when(!empty($param['name']), fn($q) => $q->where('name', 'like', $param['name'] . '%'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 获取所有启用的模型（用于下拉选择等场景）
     */
    public function getAllActive()
    {
        return $this->model
            ->select(['id', 'name', 'driver'])
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->orderBy('id', 'desc')
            ->get();
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

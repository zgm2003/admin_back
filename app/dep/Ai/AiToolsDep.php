<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Ai\AiToolsModel;
use support\Model;

/**
 * AI 工具数据访问层
 */
class AiToolsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiToolsModel();
    }

    /**
     * 分页列表（支持 name/status/executor_type 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->select(['id', 'name', 'code', 'description', 'executor_type', 'status', 'created_at', 'updated_at'])
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['name']), fn($q) => $q->where('name', 'like', $param['name'] . '%'))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->when(isset($param['executor_type']) && $param['executor_type'] !== '', fn($q) => $q->where('executor_type', (int)$param['executor_type']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 15, ['*'], 'page', $param['current_page'] ?? 1);
    }

    /**
     * 按 code 查未删除记录（唯一性校验用）
     */
    public function existsByCode(string $code, ?int $excludeId = null): bool
    {
        return $this->model
            ->where('code', $code)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * 获取所有启用工具（字典用）
     */
    public function getAllActive(): \Illuminate\Support\Collection
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->select(['id', 'name', 'code'])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * 软删除（污染 code 释放唯一约束）
     * code 改为 LEFT(code, maxPrefix) || '__del_' || id，保证总长 ≤ 60
     */
    public function softDelete($ids): int
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $affected = 0;
        foreach ($ids as $id) {
            $suffix = '__del_' . $id;
            $maxPrefix = 60 - strlen($suffix);
            $affected += $this->model
                ->where('id', $id)
                ->where('is_del', CommonEnum::NO)
                ->update([
                    'is_del' => CommonEnum::YES,
                    'code'   => \support\Db::raw("CONCAT(LEFT(code, {$maxPrefix}), '{$suffix}')"),
                ]);
        }
        return $affected;
    }
}

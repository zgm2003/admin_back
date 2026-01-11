<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiAgentModel;
use app\enum\CommonEnum;
use support\Model;

class AiAgentsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiAgentModel();
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
            ->when(!empty($param['model_id']), fn($q) => $q->where('model_id', (int)$param['model_id']))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->when(!empty($param['mode']), fn($q) => $q->where('mode', $param['mode']))
            ->when(!empty($param['name']), fn($q) => $q->where('name', 'like', '%' . $param['name'] . '%'))
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }

    /**
     * 获取所有启用的智能体（字典用）
     */
    public function allActive()
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->select(['id', 'name'])
            ->orderBy('id', 'desc')
            ->get();
    }
}

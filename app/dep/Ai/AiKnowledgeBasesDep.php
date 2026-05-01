<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Ai\AiKnowledgeBasesModel;
use support\Model;

class AiKnowledgeBasesDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiKnowledgeBasesModel();
    }

    public function list(array $param)
    {
        return $this->model
            ->select([
                'id', 'name', 'description', 'owner_user_id', 'visibility', 'permission_json',
                'chunk_size', 'chunk_overlap', 'top_k', 'score_threshold', 'status', 'created_at', 'updated_at',
            ])
            ->where('is_del', CommonEnum::NO)
            ->when(!empty(trim($param['name'] ?? '')), fn($q) => $q->where('name', 'like', '%' . trim($param['name']) . '%'))
            ->when(!empty(trim($param['visibility'] ?? '')), fn($q) => $q->where('visibility', trim($param['visibility'])))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 15, ['*'], 'page', $param['current_page'] ?? 1);
    }

    public function getAllActiveOptions(): \Illuminate\Support\Collection
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->select(['id', 'name'])
            ->orderBy('id', 'desc')
            ->get();
    }

}

<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Ai\AiKnowledgeDocumentsModel;
use support\Model;

class AiKnowledgeDocumentsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiKnowledgeDocumentsModel();
    }

    public function listByKnowledgeBase(array $param)
    {
        return $this->model
            ->select(['id', 'knowledge_base_id', 'title', 'source_type', 'chunk_count', 'index_status', 'status', 'created_at', 'updated_at'])
            ->where('knowledge_base_id', (int)$param['knowledge_base_id'])
            ->where('is_del', CommonEnum::NO)
            ->when(!empty(trim($param['title'] ?? '')), fn($q) => $q->where('title', 'like', '%' . trim($param['title']) . '%'))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 15, ['*'], 'page', $param['current_page'] ?? 1);
    }

    public function getByKnowledgeBase(int $id, int $knowledgeBaseId)
    {
        return $this->model
            ->where('id', $id)
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    public function updateChunkCount(int $id, int $chunkCount, int $indexStatus): int
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->update([
                'chunk_count' => $chunkCount,
                'index_status' => $indexStatus,
            ]);
    }

    public function deleteByKnowledgeBaseIds(array $knowledgeBaseIds): int
    {
        $knowledgeBaseIds = $this->normalizeIds($knowledgeBaseIds);
        if (empty($knowledgeBaseIds)) {
            return 0;
        }

        return $this->model
            ->whereIn('knowledge_base_id', $knowledgeBaseIds)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }
}

<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Ai\AiKnowledgeChunksModel;
use support\Db;
use support\Model;

class AiKnowledgeChunksDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiKnowledgeChunksModel();
    }

    public function replaceDocumentChunks(int $knowledgeBaseId, int $documentId, array $chunks): int
    {
        $this->model
            ->where('document_id', $documentId)
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);

        if (empty($chunks)) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($chunks as $index => $chunk) {
            $rows[] = [
                'knowledge_base_id' => $knowledgeBaseId,
                'document_id' => $documentId,
                'chunk_no' => $index + 1,
                'content' => $chunk['content'],
                'token_estimate' => (int)($chunk['token_estimate'] ?? 1),
                'metadata_json' => isset($chunk['metadata_json'])
                    ? json_encode($chunk['metadata_json'], JSON_UNESCAPED_UNICODE)
                    : null,
                'status' => CommonEnum::YES,
                'is_del' => CommonEnum::NO,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->model->insert($rows);

        return count($rows);
    }

    public function listByDocument(array $param)
    {
        return $this->model
            ->select(['id', 'knowledge_base_id', 'document_id', 'chunk_no', 'content', 'token_estimate', 'metadata_json', 'status', 'created_at'])
            ->where('knowledge_base_id', (int)$param['knowledge_base_id'])
            ->when(!empty($param['document_id']), fn($q) => $q->where('document_id', (int)$param['document_id']))
            ->where('is_del', CommonEnum::NO)
            ->orderBy('document_id', 'desc')
            ->orderBy('chunk_no')
            ->paginate($param['page_size'] ?? 15, ['*'], 'page', $param['current_page'] ?? 1);
    }

    public function getCandidateChunks(array $knowledgeBaseIds, array $terms, int $limit = 300): \Illuminate\Support\Collection
    {
        $knowledgeBaseIds = array_values(array_unique(array_filter(array_map('intval', $knowledgeBaseIds))));
        if (empty($knowledgeBaseIds)) {
            return collect();
        }

        $query = Db::table('ai_knowledge_chunks as c')
            ->join('ai_knowledge_documents as d', 'd.id', '=', 'c.document_id')
            ->whereIn('c.knowledge_base_id', $knowledgeBaseIds)
            ->where('c.is_del', CommonEnum::NO)
            ->where('c.status', CommonEnum::YES)
            ->where('d.is_del', CommonEnum::NO)
            ->where('d.status', CommonEnum::YES)
            ->select([
                'c.id', 'c.knowledge_base_id', 'c.document_id', 'c.chunk_no',
                'c.content', 'c.token_estimate', 'd.title as document_title',
            ]);

        $terms = array_slice(array_values(array_filter($terms)), 0, 8);
        if (!empty($terms)) {
            $query->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhere('c.content', 'like', '%' . addcslashes($term, '%_\\') . '%');
                }
            });
        }

        return $query
            ->orderBy('c.id', 'desc')
            ->limit($limit)
            ->get();
    }

    public function deleteByDocument(int $knowledgeBaseId, int $documentId): int
    {
        return $this->model
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->where('document_id', $documentId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
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

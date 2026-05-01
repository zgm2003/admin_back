<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentKnowledgeBasesDep;
use app\dep\Ai\AiKnowledgeBasesDep;
use app\dep\Ai\AiKnowledgeChunksDep;
use app\dep\Ai\AiKnowledgeDocumentsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Ai\AiRagService;
use app\service\Common\DictService;
use app\validate\Ai\AiKnowledgeBasesValidate;

class AiKnowledgeBasesModule extends BaseModule
{
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setCommonStatusArr()
            ->setAiKnowledgeVisibilityArr()
            ->setAiKnowledgeIndexStatusArr()
            ->setAiKnowledgeSourceTypeArr()
            ->getDict();

        return self::success($data);
    }

    public function list($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::list());
        $res = $this->dep(AiKnowledgeBasesDep::class)->list($param);
        $list = $res->map(fn($item) => $this->formatKnowledgeBase($item));

        return self::paginate($list, [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ]);
    }

    public function detail($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::detail());
        $row = $this->dep(AiKnowledgeBasesDep::class)->get((int)$param['id']);
        self::throwNotFound($row, '知识库不存在');

        return self::success($this->formatKnowledgeBase($row));
    }

    public function add($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::add());
        $chunkSize = (int)($param['chunk_size'] ?? 800);
        $chunkOverlap = (int)($param['chunk_overlap'] ?? 120);
        $this->assertChunkConfig($chunkSize, $chunkOverlap);

        $id = $this->dep(AiKnowledgeBasesDep::class)->add([
            'name' => $param['name'],
            'description' => $param['description'] ?? null,
            'owner_user_id' => (int)($request->userId ?? 0),
            'visibility' => $param['visibility'] ?? AiEnum::KNOWLEDGE_VISIBILITY_PRIVATE,
            'permission_json' => $this->encodeJson($param['permission_json'] ?? []),
            'chunk_size' => $chunkSize,
            'chunk_overlap' => $chunkOverlap,
            'top_k' => (int)($param['top_k'] ?? 5),
            'score_threshold' => (float)($param['score_threshold'] ?? 0),
            'status' => (int)($param['status'] ?? CommonEnum::YES),
            'is_del' => CommonEnum::NO,
        ]);

        return self::success(['id' => $id]);
    }

    public function edit($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::edit());

        $id = (int)$param['id'];
        $dep = $this->dep(AiKnowledgeBasesDep::class);
        $row = $dep->get($id);
        self::throwNotFound($row, '知识库不存在');
        $this->assertChunkConfig(
            (int)($param['chunk_size'] ?? $row->chunk_size),
            (int)($param['chunk_overlap'] ?? $row->chunk_overlap)
        );

        $data = [];
        foreach (['name', 'description', 'visibility', 'chunk_size', 'chunk_overlap', 'top_k', 'score_threshold', 'status'] as $field) {
            if (array_key_exists($field, $param)) {
                $data[$field] = match ($field) {
                    'chunk_size', 'chunk_overlap', 'top_k', 'status' => (int)$param[$field],
                    'score_threshold' => (float)$param[$field],
                    default => $param[$field],
                };
            }
        }
        if (array_key_exists('permission_json', $param)) {
            $data['permission_json'] = $this->encodeJson($param['permission_json']);
        }

        $dep->update($id, $data);

        return self::success();
    }

    public function del($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::del());
        $ids = is_array($param['id']) ? array_map('intval', $param['id']) : [(int)$param['id']];

        $affected = $this->withTransaction(function () use ($ids): int {
            $affected = $this->dep(AiKnowledgeBasesDep::class)->delete($ids);
            $this->dep(AiKnowledgeDocumentsDep::class)->deleteByKnowledgeBaseIds($ids);
            $this->dep(AiKnowledgeChunksDep::class)->deleteByKnowledgeBaseIds($ids);
            $this->dep(AiAgentKnowledgeBasesDep::class)->deleteByKnowledgeBaseIds($ids);

            return $affected;
        });

        return self::success(['affected' => $affected]);
    }

    public function status($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::status());
        $affected = $this->dep(AiKnowledgeBasesDep::class)->setStatus($param['id'], (int)$param['status']);

        return self::success(['affected' => $affected]);
    }

    public function documents($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::documents());
        $this->assertKnowledgeBaseExists((int)$param['knowledge_base_id']);

        $res = $this->dep(AiKnowledgeDocumentsDep::class)->listByKnowledgeBase($param);
        $list = $res->map(fn($item) => $this->formatDocument($item));

        return self::paginate($list, [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ]);
    }

    public function addDocument($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::addDocument());
        $kb = $this->assertKnowledgeBaseExists((int)$param['knowledge_base_id']);

        $id = $this->withTransaction(function () use ($param, $kb): int {
            $documentId = $this->dep(AiKnowledgeDocumentsDep::class)->add([
                'knowledge_base_id' => (int)$param['knowledge_base_id'],
                'title' => $param['title'],
                'source_type' => $param['source_type'] ?? AiEnum::KNOWLEDGE_SOURCE_MANUAL,
                'content' => $param['content'],
                'chunk_count' => 0,
                'index_status' => AiEnum::KNOWLEDGE_INDEX_FAILED,
                'status' => (int)($param['status'] ?? CommonEnum::YES),
                'is_del' => CommonEnum::NO,
            ]);

            $this->reindexDocumentRow($kb, $documentId, $param['content']);

            return $documentId;
        });

        return self::success(['id' => $id]);
    }

    public function documentDetail($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::documentDetail());
        $doc = $this->dep(AiKnowledgeDocumentsDep::class)->getByKnowledgeBase((int)$param['id'], (int)$param['knowledge_base_id']);
        self::throwNotFound($doc, '文档不存在');

        return self::success($this->formatDocument($doc) + [
            'content' => $doc->content,
        ]);
    }

    public function editDocument($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::editDocument());
        $kb = $this->assertKnowledgeBaseExists((int)$param['knowledge_base_id']);
        $documentId = (int)$param['id'];
        $doc = $this->dep(AiKnowledgeDocumentsDep::class)->getByKnowledgeBase($documentId, (int)$param['knowledge_base_id']);
        self::throwNotFound($doc, '文档不存在');

        $this->withTransaction(function () use ($param, $documentId, $kb, $doc): void {
            $data = [];
            foreach (['title', 'source_type', 'content', 'status'] as $field) {
                if (array_key_exists($field, $param)) {
                    $data[$field] = $field === 'status' ? (int)$param[$field] : $param[$field];
                }
            }
            if (!empty($data)) {
                $this->dep(AiKnowledgeDocumentsDep::class)->update($documentId, $data);
            }

            if (array_key_exists('content', $param)) {
                $this->reindexDocumentRow($kb, $documentId, (string)$param['content']);
            } elseif (array_key_exists('title', $param)) {
                $this->reindexDocumentRow($kb, $documentId, (string)$doc->content);
            }
        });

        return self::success();
    }

    public function delDocument($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::delDocument());
        $knowledgeBaseId = (int)$param['knowledge_base_id'];
        $documentId = (int)$param['id'];
        $doc = $this->dep(AiKnowledgeDocumentsDep::class)->getByKnowledgeBase($documentId, $knowledgeBaseId);
        self::throwNotFound($doc, '文档不存在');

        $affected = $this->withTransaction(function () use ($knowledgeBaseId, $documentId): int {
            $affected = $this->dep(AiKnowledgeDocumentsDep::class)->delete($documentId);
            $this->dep(AiKnowledgeChunksDep::class)->deleteByDocument($knowledgeBaseId, $documentId);

            return $affected;
        });

        return self::success(['affected' => $affected]);
    }

    public function reindexDocument($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::reindexDocument());
        $kb = $this->assertKnowledgeBaseExists((int)$param['knowledge_base_id']);
        $documentId = (int)$param['id'];
        $doc = $this->dep(AiKnowledgeDocumentsDep::class)->getByKnowledgeBase($documentId, (int)$param['knowledge_base_id']);
        self::throwNotFound($doc, '文档不存在');

        $chunkCount = $this->withTransaction(fn() => $this->reindexDocumentRow($kb, $documentId, (string)$doc->content));

        return self::success(['chunk_count' => $chunkCount]);
    }

    public function chunks($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::chunks());
        $this->assertKnowledgeBaseExists((int)$param['knowledge_base_id']);

        $res = $this->dep(AiKnowledgeChunksDep::class)->listByDocument($param);
        $list = $res->map(fn($item) => $this->formatChunk($item));

        return self::paginate($list, [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ]);
    }

    public function retrievalTest($request): array
    {
        $param = $this->validate($request, AiKnowledgeBasesValidate::retrievalTest());
        $kb = $this->assertKnowledgeBaseExists((int)$param['knowledge_base_id']);

        $topK = (int)($param['top_k'] ?? $kb->top_k ?? 5);
        $chunks = AiRagService::retrieveFromKnowledgeBases(
            [(int)$kb->id],
            $param['query'],
            $topK,
            (float)($kb->score_threshold ?? 0)
        );

        return self::success([
            'chunks' => $chunks,
            'context_prompt' => AiRagService::buildContextPrompt($chunks),
        ]);
    }

    private function assertKnowledgeBaseExists(int $id): object
    {
        $row = $this->dep(AiKnowledgeBasesDep::class)->get($id);
        self::throwNotFound($row, '知识库不存在');

        return $row;
    }

    private function reindexDocumentRow(object $kb, int $documentId, string $content): int
    {
        $rawChunks = AiRagService::chunkText((string)$content, (int)$kb->chunk_size, (int)$kb->chunk_overlap);
        $chunks = array_map(static fn(string $chunk) => [
            'content' => $chunk,
            'token_estimate' => AiRagService::tokenEstimate($chunk),
            'metadata_json' => ['source' => 'mysql_keyword'],
        ], $rawChunks);

        $chunkCount = $this->dep(AiKnowledgeChunksDep::class)->replaceDocumentChunks((int)$kb->id, $documentId, $chunks);
        $this->dep(AiKnowledgeDocumentsDep::class)->updateChunkCount(
            $documentId,
            $chunkCount,
            $chunkCount > 0 ? AiEnum::KNOWLEDGE_INDEX_INDEXED : AiEnum::KNOWLEDGE_INDEX_FAILED
        );

        return $chunkCount;
    }

    private function assertChunkConfig(int $chunkSize, int $chunkOverlap): void
    {
        self::throwIf($chunkOverlap >= $chunkSize, '切片重叠必须小于切片长度');
    }

    private function formatKnowledgeBase(object $item): array
    {
        return [
            'id' => (int)$item->id,
            'name' => $item->name,
            'description' => $item->description,
            'owner_user_id' => (int)$item->owner_user_id,
            'visibility' => $item->visibility,
            'visibility_name' => AiEnum::$knowledgeVisibilityArr[$item->visibility] ?? $item->visibility,
            'permission_json' => $this->decodeJson($item->permission_json ?? []),
            'chunk_size' => (int)$item->chunk_size,
            'chunk_overlap' => (int)$item->chunk_overlap,
            'top_k' => (int)$item->top_k,
            'score_threshold' => (float)$item->score_threshold,
            'status' => (int)$item->status,
            'status_name' => CommonEnum::$statusArr[$item->status] ?? '',
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }

    private function formatDocument(object $item): array
    {
        return [
            'id' => (int)$item->id,
            'knowledge_base_id' => (int)$item->knowledge_base_id,
            'title' => $item->title,
            'source_type' => $item->source_type,
            'source_type_name' => AiEnum::$knowledgeSourceTypeArr[$item->source_type] ?? $item->source_type,
            'chunk_count' => (int)$item->chunk_count,
            'index_status' => (int)$item->index_status,
            'index_status_name' => AiEnum::$knowledgeIndexStatusArr[$item->index_status] ?? '',
            'status' => (int)$item->status,
            'status_name' => CommonEnum::$statusArr[$item->status] ?? '',
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }

    private function formatChunk(object $item): array
    {
        return [
            'id' => (int)$item->id,
            'knowledge_base_id' => (int)$item->knowledge_base_id,
            'document_id' => (int)$item->document_id,
            'chunk_no' => (int)$item->chunk_no,
            'content' => $item->content,
            'token_estimate' => (int)$item->token_estimate,
            'metadata_json' => $this->decodeJson($item->metadata_json ?? []),
            'status' => (int)$item->status,
            'created_at' => $item->created_at,
        ];
    }

    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}

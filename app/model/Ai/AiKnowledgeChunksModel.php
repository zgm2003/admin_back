<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiKnowledgeChunksModel extends BaseModel
{
    protected $table = 'ai_knowledge_chunks';

    protected $casts = [
        'knowledge_base_id' => 'integer',
        'document_id' => 'integer',
        'chunk_no' => 'integer',
        'token_estimate' => 'integer',
        'metadata_json' => 'json',
        'embedding_dim' => 'integer',
        'embedding_json' => 'json',
        'status' => 'integer',
        'is_del' => 'integer',
    ];
}

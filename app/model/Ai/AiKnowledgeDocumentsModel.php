<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiKnowledgeDocumentsModel extends BaseModel
{
    protected $table = 'ai_knowledge_documents';

    protected $casts = [
        'knowledge_base_id' => 'integer',
        'chunk_count' => 'integer',
        'index_status' => 'integer',
        'status' => 'integer',
        'is_del' => 'integer',
    ];
}

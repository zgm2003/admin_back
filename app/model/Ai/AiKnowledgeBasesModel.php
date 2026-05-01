<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiKnowledgeBasesModel extends BaseModel
{
    protected $table = 'ai_knowledge_bases';

    protected $casts = [
        'permission_json' => 'json',
        'chunk_size' => 'integer',
        'chunk_overlap' => 'integer',
        'top_k' => 'integer',
        'score_threshold' => 'float',
        'status' => 'integer',
        'is_del' => 'integer',
    ];
}

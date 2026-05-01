<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiAgentKnowledgeBasesModel extends BaseModel
{
    protected $table = 'ai_agent_knowledge_bases';

    protected $casts = [
        'agent_id' => 'integer',
        'knowledge_base_id' => 'integer',
        'config_json' => 'json',
        'status' => 'integer',
        'is_del' => 'integer',
    ];
}

<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiAgentModel extends BaseModel
{
    protected $table = 'ai_agents';

    protected $casts = [
        'extra_params' => 'array',
    ];

    protected $hidden = [
        'is_del',
    ];
}

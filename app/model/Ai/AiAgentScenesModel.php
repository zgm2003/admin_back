<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiAgentScenesModel extends BaseModel
{
    protected $table = 'ai_agent_scenes';

    protected $casts = [
        'config_json' => 'json',
    ];

    protected $hidden = [
        'is_del',
    ];
}

<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiAgentsModel extends BaseModel
{
    protected $table = 'ai_agents';

    protected $casts = [
        'capabilities_json' => 'json',
        'runtime_config_json' => 'json',
        'policy_json' => 'json',
    ];

    protected $hidden = [
        'is_del',
    ];
}

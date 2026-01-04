<?php

namespace app\model\Ai;

use support\Model;

class AiAgentModel extends Model
{
    protected $table = 'ai_agents';

    protected $casts = [
        'extra_params' => 'array',
    ];

    protected $hidden = [
        'is_del',
    ];
}

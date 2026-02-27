<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiAgentsModel extends BaseModel
{
    protected $table = 'ai_agents';

    protected $casts = [];

    protected $hidden = [
        'is_del',
    ];
}

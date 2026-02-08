<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiRunStepModel extends BaseModel
{
    protected $table = 'ai_run_steps';

    protected $casts = [
        'payload_json' => 'array',
    ];

    protected $hidden = [
        'is_del',
    ];
}

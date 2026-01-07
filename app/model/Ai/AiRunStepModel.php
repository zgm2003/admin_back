<?php

namespace app\model\Ai;

use support\Model;

class AiRunStepModel extends Model
{
    protected $table = 'ai_run_steps';

    protected $casts = [
        'payload_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'is_del',
    ];
}

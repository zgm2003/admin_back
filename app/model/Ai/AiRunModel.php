<?php

namespace app\model\Ai;

use support\Model;

class AiRunModel extends Model
{
    protected $table = 'ai_runs';

    protected $casts = [
        'meta_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'is_del',
    ];
}

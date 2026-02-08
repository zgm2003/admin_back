<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiRunModel extends BaseModel
{
    protected $table = 'ai_runs';

    protected $casts = [
        'meta_json' => 'array',
    ];

    protected $hidden = [
        'is_del',
    ];
}

<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiMessageModel extends BaseModel
{
    protected $table = 'ai_messages';

    protected $casts = [
        'meta_json' => 'array',
    ];

    protected $hidden = [
        'is_del',
    ];
}

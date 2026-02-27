<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiAssistantToolsModel extends BaseModel
{
    protected $table = 'ai_assistant_tools';

    protected $casts = [
        'config_json' => 'json',
    ];
}

<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiToolsModel extends BaseModel
{
    protected $table = 'ai_tools';

    protected $casts = [
        'schema_json'     => 'json',
        'executor_config' => 'json',
    ];
}

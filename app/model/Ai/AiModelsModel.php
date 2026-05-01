<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiModelsModel extends BaseModel
{
    /**
     * 表名
     */
    protected $table = 'ai_models';

    /**
     * 隐藏字段（不直接对外暴露加密后的 key）
     */
    protected $hidden = [
        'api_key_enc',
    ];
}

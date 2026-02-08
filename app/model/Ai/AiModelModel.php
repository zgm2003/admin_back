<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiModelModel extends BaseModel
{
    /**
     * 表名
     */
    protected $table = 'ai_models';

    /**
     * 类型转换
     */
    protected $casts = [
        'default_params' => 'array',
        'modalities' => 'array',
    ];

    /**
     * 隐藏字段（不直接对外暴露加密后的 key）
     */
    protected $hidden = [
        'api_key_enc',
    ];
}

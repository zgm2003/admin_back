<?php

namespace app\model\Ai;

use app\model\BaseModel;

class CineAssetModel extends BaseModel
{
    protected $table = 'cine_assets';

    protected $casts = [
        'project_id' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'meta_json' => 'json',
        'is_del' => 'integer',
    ];
}

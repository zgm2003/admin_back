<?php

namespace app\model\Ai;

use app\model\BaseModel;

class GoodsModel extends BaseModel
{
    protected $table = 'goods';

    protected $casts = [
        'image_list' => 'array',
        'image_list_success' => 'array',
    ];
}

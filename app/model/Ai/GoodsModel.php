<?php

namespace app\model\Ai;

use app\model\BaseModel;

class GoodsModel extends BaseModel
{
    protected $table = 'goods';

    protected $casts = [
        'image_list' => 'json',
        'image_list_success' => 'json',
        'meta' => 'json',
    ];
}

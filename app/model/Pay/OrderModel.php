<?php

namespace app\model\Pay;

use app\model\BaseModel;

class OrderModel extends BaseModel
{
    protected $table = 'orders';

    protected $casts = [
        'extra' => 'json',
    ];
}

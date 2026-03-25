<?php

namespace app\model\Pay;

use app\model\BaseModel;

class PayRefundModel extends BaseModel
{
    protected $table = 'pay_refunds';

    protected $casts = [
        'raw_request' => 'json',
        'raw_notify'  => 'json',
    ];
}

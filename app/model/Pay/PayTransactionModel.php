<?php

namespace app\model\Pay;

use app\model\BaseModel;

class PayTransactionModel extends BaseModel
{
    protected $table = 'pay_transactions';

    protected $casts = [
        'channel_resp' => 'json',
        'raw_notify'   => 'json',
    ];
}

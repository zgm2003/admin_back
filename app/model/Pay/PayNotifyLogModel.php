<?php

namespace app\model\Pay;

use app\model\BaseModel;

class PayNotifyLogModel extends BaseModel
{
    protected $table = 'pay_notify_logs';

    protected $casts = [
        'headers'  => 'json',
        'raw_data' => 'json',
    ];
}

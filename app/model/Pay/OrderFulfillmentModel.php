<?php

namespace app\model\Pay;

use app\model\BaseModel;

class OrderFulfillmentModel extends BaseModel
{
    protected $table = 'order_fulfillments';

    protected $casts = [
        'request_payload' => 'json',
        'result_payload'  => 'json',
    ];
}

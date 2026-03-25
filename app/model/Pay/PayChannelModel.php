<?php

namespace app\model\Pay;

use app\model\BaseModel;

class PayChannelModel extends BaseModel
{
    protected $table = 'pay_channel';

    protected $casts = [
        'extra_config' => 'json',
    ];

    protected $hidden = [
        'app_private_key_enc',
    ];
}

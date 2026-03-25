<?php

namespace app\model\Pay;

use app\model\BaseModel;

class WalletTransactionModel extends BaseModel
{
    protected $table = 'wallet_transactions';

    protected $casts = [
        'ext' => 'json',
    ];
}

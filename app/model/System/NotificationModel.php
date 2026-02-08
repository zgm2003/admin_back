<?php

namespace app\model\System;

use support\Model;

class NotificationModel extends Model
{
    protected $table = 'notifications';

    /** created_at / updated_at 由 MySQL DEFAULT 自动维护，Eloquent 不干预 */
    public $timestamps = false;
}

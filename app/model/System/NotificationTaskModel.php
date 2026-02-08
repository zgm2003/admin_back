<?php

namespace app\model\System;

use support\Model;

class NotificationTaskModel extends Model
{
    protected $table = 'notification_task';

    /** created_at / updated_at 由 MySQL DEFAULT 自动维护 */
    public $timestamps = false;
}

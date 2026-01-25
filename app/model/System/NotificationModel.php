<?php

namespace app\model\System;

use support\Model;

class NotificationModel extends Model
{
    protected $table = 'notifications';
    
    protected $fillable = [
        'user_id', 'title', 'content', 'type', 'level', 'link', 'is_read', 'is_del'
    ];
}

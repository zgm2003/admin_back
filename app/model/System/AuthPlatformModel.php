<?php

namespace app\model\System;

use app\model\BaseModel;

class AuthPlatformModel extends BaseModel
{
    protected $table = 'auth_platforms';

    protected $casts = [
        'login_types' => 'json',
    ];
}

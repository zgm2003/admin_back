<?php

namespace app\model\User;

use support\Model;

class UserProfileModel extends Model
{
    public $table = 'user_profiles';
    public $primaryKey = 'user_id';
}


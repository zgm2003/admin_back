<?php

namespace app\model\User;

use app\model\BaseModel;

class UserProfileModel extends BaseModel
{
    protected $table = 'user_profiles';
    public $primaryKey = 'user_id';
}


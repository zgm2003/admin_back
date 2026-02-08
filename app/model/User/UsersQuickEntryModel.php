<?php

namespace app\model\User;

use app\model\BaseModel;

class UsersQuickEntryModel extends BaseModel
{
    protected $table = 'users_quick_entry';

    protected $fillable = ['user_id', 'permission_id', 'sort'];

    protected $hidden = ['user_id', 'sort', 'is_del', 'created_at', 'updated_at'];
}

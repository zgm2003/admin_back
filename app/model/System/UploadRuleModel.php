<?php

namespace app\model\System;

use app\model\BaseModel;

class UploadRuleModel extends BaseModel
{
    protected $table = 'upload_rule';

    protected $casts = [
        'image_exts' => 'json',
        'file_exts' => 'json',
    ];
}

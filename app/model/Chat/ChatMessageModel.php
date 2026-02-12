<?php

namespace app\model\Chat;

use app\model\BaseModel;

class ChatMessageModel extends BaseModel
{
    protected $table = 'chat_messages';

    protected $casts = [
        'meta_json' => 'array',
    ];
}

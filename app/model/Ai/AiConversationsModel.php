<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiConversationsModel extends BaseModel
{
    protected $table = 'ai_conversations';

    protected $casts = [
        'last_message_at' => 'datetime',
    ];
}

<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiConversationModel extends BaseModel
{
    protected $table = 'ai_conversations';

    protected $casts = [
        'last_message_at' => 'datetime',
    ];
}

<?php

namespace app\model\Ai;

use support\Model;

class AiConversationModel extends Model
{
    protected $table = 'ai_conversations';

    protected $casts = [
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

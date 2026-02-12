<?php

namespace app\model\Chat;

use app\model\BaseModel;

class ChatConversationModel extends BaseModel
{
    protected $table = 'chat_conversations';

    protected $casts = [
        'last_message_at' => 'datetime',
    ];
}

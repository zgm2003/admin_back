<?php

namespace app\dep\Chat;

use app\dep\BaseDep;
use app\model\Chat\ChatMessageModel;
use support\Model;

class ChatMessageDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new ChatMessageModel();
    }

    /**
     * 添加消息，返回新消息 ID
     *
     * @param array $data [conversation_id, sender_id, type, content, meta_json]
     */
    public function addMessage(array $data): int
    {
        return $this->add($data);
    }

    /**
     * 游标分页查询会话消息历史
     * 使用 BaseDep::listCursor，checkDel=true（消息表使用 is_del 字段）
     *
     * @param int $conversationId 会话 ID
     * @param int|null $cursor 游标（上一页最后一条消息的 ID），null 表示从最新开始
     * @param int $limit 每页数量
     * @return array ['list' => Collection, 'next_cursor' => int|null, 'has_more' => bool]
     */
    public function listByConversation(int $conversationId, ?int $cursor, int $limit = 20): array
    {
        return $this->listCursor(
            ['page_size' => $limit, 'cursor' => $cursor],
            fn($q) => $q->where('conversation_id', $conversationId),
            ['*'],
            true
        );
    }
}

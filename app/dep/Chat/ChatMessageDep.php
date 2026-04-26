<?php

namespace app\dep\Chat;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Chat\ChatMessageModel;
use Illuminate\Pagination\LengthAwarePaginator;
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

    /** 会话消息历史（普通分页，按最新消息倒序） */
    public function listByConversation(int $conversationId, int $currentPage, int $pageSize = 20): LengthAwarePaginator
    {
        return $this->model
            ->where('conversation_id', $conversationId)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }
}

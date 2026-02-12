<?php

namespace app\dep\Chat;

use app\dep\BaseDep;
use app\model\Chat\ChatConversationModel;
use app\enum\ChatEnum;
use app\enum\CommonEnum;
use support\Model;

class ChatConversationDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new ChatConversationModel();
    }

    /**
     * 查找两人之间的私聊会话
     * 通过 JOIN chat_participants 两次，确认双方都在同一个私聊会话中
     *
     * @return object|null
     */
    public function findPrivateConversation(int $userIdA, int $userIdB)
    {
        return $this->query()
            ->from('chat_conversations as c')
            ->join('chat_participants as pa', function ($join) use ($userIdA) {
                $join->on('pa.conversation_id', '=', 'c.id')
                    ->where('pa.user_id', $userIdA)
                    ->where('pa.status', ChatEnum::PARTICIPANT_ACTIVE);
            })
            ->join('chat_participants as pb', function ($join) use ($userIdB) {
                $join->on('pb.conversation_id', '=', 'c.id')
                    ->where('pb.user_id', $userIdB)
                    ->where('pb.status', ChatEnum::PARTICIPANT_ACTIVE);
            })
            ->where('c.type', ChatEnum::CONVERSATION_PRIVATE)
            ->where('c.is_del', CommonEnum::NO)
            ->select('c.*')
            ->first();
    }

    /**
     * 创建会话，返回新会话 ID
     */
    public function createConversation(array $data): int
    {
        return $this->add($data);
    }

    /**
     * 更新会话的最后消息信息
     */
    public function updateLastMessage(int $conversationId, int $messageId, string $messageAt, string $preview): int
    {
        return $this->update($conversationId, [
            'last_message_id' => $messageId,
            'last_message_at' => $messageAt,
            'last_message_preview' => mb_substr($preview, 0, 200),
        ]);
    }

    /**
     * 用户会话列表查询
     * JOIN chat_participants 过滤当前用户参与的、未删除的、状态正常的会话
     * 按 last_message_at 降序排列
     *
     * @return \Illuminate\Support\Collection
     */
    public function listByUser(int $userId)
    {
        return $this->query()
            ->from('chat_conversations as c')
            ->join('chat_participants as p', function ($join) {
                $join->on('p.conversation_id', '=', 'c.id')
                    ->where('p.is_del', CommonEnum::NO)
                    ->where('p.status', ChatEnum::PARTICIPANT_ACTIVE);
            })
            ->where('p.user_id', $userId)
            ->where('c.is_del', CommonEnum::NO)
            ->select('c.*', 'p.role', 'p.last_read_message_id', 'p.is_pinned')
            ->orderByRaw('p.is_pinned = 1 DESC')
            ->orderBy('c.last_message_at', 'desc')
            ->get();
    }
}

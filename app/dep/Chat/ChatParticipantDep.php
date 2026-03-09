<?php

namespace app\dep\Chat;

use app\dep\BaseDep;
use app\model\Chat\ChatParticipantModel;
use app\enum\ChatEnum;
use app\enum\CommonEnum;
use support\Model;

class ChatParticipantDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new ChatParticipantModel();
    }

    /**
     * 批量添加参与者
     *
     * @param array $participants 每项包含 conversation_id, user_id, role
     * @return int
     */
    public function addBatch(array $participants): int
    {
        if (empty($participants)) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $rows = array_map(function ($item) use ($now) {
            return [
                'conversation_id' => $item['conversation_id'],
                'user_id' => $item['user_id'],
                'role' => $item['role'] ?? ChatEnum::ROLE_MEMBER,
                'status' => ChatEnum::PARTICIPANT_ACTIVE,
                'is_del' => CommonEnum::NO,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $participants);

        return $this->query()->insert($rows) ? count($rows) : 0;
    }

    /**
     * 查询会话的活跃参与者（is_del=NO, status=ACTIVE）
     *
     * @return \Illuminate\Support\Collection
     */
    public function getActiveParticipants(int $conversationId)
    {
        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('is_del', CommonEnum::NO)
            ->where('status', ChatEnum::PARTICIPANT_ACTIVE)
            ->get();
    }

    /**
     * 查询会话的活跃参与者（含 username、avatar）
     *
     * @return \Illuminate\Support\Collection
     */
    public function getActiveParticipantsWithProfile(int $conversationId)
    {
        return $this->query()
            ->join('users', 'users.id', '=', 'chat_participants.user_id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'chat_participants.user_id')
            ->where('chat_participants.conversation_id', $conversationId)
            ->where('chat_participants.is_del', CommonEnum::NO)
            ->where('chat_participants.status', ChatEnum::PARTICIPANT_ACTIVE)
            ->select([
                'chat_participants.id',
                'chat_participants.conversation_id',
                'chat_participants.user_id',
                'chat_participants.role',
                'chat_participants.status',
                'chat_participants.created_at',
                'users.username',
                'user_profiles.avatar',
            ])
            ->get();
    }

    /**
     * 查询用户参与的会话 ID 列表（活跃状态）
     *
     * @return array
     */
    public function getUserConversationIds(int $userId): array
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->where('status', ChatEnum::PARTICIPANT_ACTIVE)
            ->pluck('conversation_id')
            ->toArray();
    }

    /**
     * 更新参与者状态（用于踢出/退出）
     */
    public function updateStatus(int $conversationId, int $userId, int $status): int
    {
        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['status' => $status]);
    }

    /**
     * 更新参与者角色（用于转让群主）
     */
    public function updateRole(int $conversationId, int $userId, int $role): int
    {
        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['role' => $role]);
    }

    /**
     * 检查用户是否为会话的活跃参与者
     */
    public function isParticipant(int $conversationId, int $userId): bool
    {
        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->where('status', ChatEnum::PARTICIPANT_ACTIVE)
            ->exists();
    }

    /**
     * 获取指定会话中的指定用户参与者记录
     *
     * @return object|null
     */
    public function getParticipant(int $conversationId, int $userId)
    {
        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * 更新参与者的最后已读消息ID
     */
    public function updateLastReadMessageId(int $conversationId, int $userId, int $messageId): int
    {
        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_message_id' => $messageId]);
    }

    /**
     * 软删除参与者记录（用户删除会话时，设置 is_del=YES）
     */
    public function softDelete(int $conversationId, int $userId): int
    {
        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }

    /**
     * 切换会话置顶状态
     */
    public function togglePin(int $conversationId, int $userId): int
    {
        $current = $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->value('is_pinned');

        $newVal = ($current == CommonEnum::YES) ? CommonEnum::NO : CommonEnum::YES;

        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['is_pinned' => $newVal]);
    }

    /**
     * 批量查询私聊会话中对方用户的信息（username、avatar）
     * 返回 conversationId => ['username' => ..., 'avatar' => ...] 的映射
     * 不限制参与者状态，确保对方退出后私聊仍能显示对方信息
     *
     * @param array $conversationIds 私聊会话 ID 列表
     * @param int $currentUserId 当前用户 ID（排除自己）
     * @return array
     */
    public function getPeersForPrivateConversations(array $conversationIds, int $currentUserId): array
    {
        if (empty($conversationIds)) {
            return [];
        }

        $peers = $this->query()
            ->join('users', 'users.id', '=', 'chat_participants.user_id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'chat_participants.user_id')
            ->whereIn('chat_participants.conversation_id', $conversationIds)
            ->where('chat_participants.user_id', '!=', $currentUserId)
            ->select([
                'chat_participants.conversation_id',
                'users.username',
                'user_profiles.avatar',
            ])
            ->get();

        $map = [];
        foreach ($peers as $peer) {
            $map[$peer->conversation_id] = [
                'username' => $peer->username,
                'avatar'   => $peer->avatar ?? '',
            ];
        }

        return $map;
    }

    /**
     * 批量恢复非活跃参与者（KICKED/LEFT → ACTIVE）
     *
     * @param int $conversationId
     * @param array $userIds
     * @return int 恢复的行数
     */
    public function reactivateBatch(int $conversationId, array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return $this->query()
            ->where('conversation_id', $conversationId)
            ->whereIn('user_id', $userIds)
            ->where('status', '!=', ChatEnum::PARTICIPANT_ACTIVE)
            ->update([
                'status' => ChatEnum::PARTICIPANT_ACTIVE,
                'role' => ChatEnum::ROLE_MEMBER,
                'is_del' => CommonEnum::NO,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 获取会话中已存在但非活跃的参与者 user_id 列表（KICKED/LEFT）
     *
     * @return array
     */
    public function getInactiveUserIds(int $conversationId, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return $this->query()
            ->where('conversation_id', $conversationId)
            ->whereIn('user_id', $userIds)
            ->where('status', '!=', ChatEnum::PARTICIPANT_ACTIVE)
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * 恢复用户已软删除的参与者记录（is_del=YES → NO）
     * 用于用户重新打开已删除的私聊会话
     */
    public function restoreDeleted(int $conversationId, int $userId): int
    {
        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::YES)
            ->update(['is_del' => CommonEnum::NO]);
    }

    /**
     * 使用行锁查询参与者记录（用于并发控制）
     *
     * @return object|null
     */
    public function getParticipantForUpdate(int $conversationId, int $userId)
    {
        return $this->query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
    }

}

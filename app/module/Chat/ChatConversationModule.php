<?php

namespace app\module\Chat;

use app\dep\Chat\ChatContactDep;
use app\dep\Chat\ChatConversationDep;
use app\dep\Chat\ChatParticipantDep;
use app\dep\User\UsersDep;
use app\enum\ChatEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Chat\ChatService;
use app\validate\Chat\ChatValidate;
use Respect\Validation\Validator as v;

/**
 * 聊天会话管理模块
 * 负责：私聊创建（幂等）、群聊创建、会话列表、删除会话、置顶切换
 */
class ChatConversationModule extends BaseModule
{
    /**
     * 创建/获取私聊会话（幂等）
     * 两人之间已存在私聊则直接返回，否则创建新会话
     * 必须是已确认的联系人才能发起私聊
     */
    public function createPrivate($request): array
    {
        $param = $this->validate($request, ChatValidate::createPrivate());
        $currentUserId = $request->userId;
        $targetUserId = (int)$param['user_id'];

        self::throwIf($currentUserId === $targetUserId, '不能与自己创建私聊');
        $this->dep(UsersDep::class)->findOrFail($targetUserId);

        // 必须是已确认的联系人
        self::throwUnless(
            $this->dep(ChatContactDep::class)->isConfirmedContact($currentUserId, $targetUserId),
            '对方不是你的联系人，请先添加联系人'
        );

        // 事务内查找+创建，防止并发创建重复私聊会话
        $convDep = $this->dep(ChatConversationDep::class);
        $partDep = $this->dep(ChatParticipantDep::class);

        $conversation = $this->withTransaction(function () use ($convDep, $partDep, $currentUserId, $targetUserId) {
            // 加锁查找已有私聊会话
            $existing = $convDep->findPrivateConversationForUpdate($currentUserId, $targetUserId);
            if ($existing) {
                $partDep->restoreDeleted($existing->id, $currentUserId);
                return $existing;
            }

            $conversationId = $convDep->createConversation([
                'type'         => ChatEnum::CONVERSATION_PRIVATE,
                'name'         => '',
                'owner_id'     => 0,
                'member_count' => 2,
                'is_del'       => CommonEnum::NO,
            ]);

            $partDep->addBatch([
                ['conversation_id' => $conversationId, 'user_id' => $currentUserId, 'role' => ChatEnum::ROLE_MEMBER],
                ['conversation_id' => $conversationId, 'user_id' => $targetUserId, 'role' => ChatEnum::ROLE_MEMBER],
            ]);

            return $convDep->find($conversationId);
        });

        return self::success(['conversation' => $conversation->toArray()]);
    }

    /**
     * 创建群聊
     * 创建者自动成为群主，指定成员添加为普通成员，至少选择 1 名好友
     */
    public function createGroup($request): array
    {
        $param = $this->validate($request, ChatValidate::createGroup());
        $currentUserId = $request->userId;
        $memberIds = \array_map('intval', $param['user_ids']);

        // 去重并排除创建者自身
        $memberIds = \array_values(\array_unique(\array_filter($memberIds, fn($id) => $id !== $currentUserId)));
        self::throwIf(\count($memberIds) < 1, '群聊至少需要选择1名好友');

        // 校验用户真实存在
        $existingUsers = $this->dep(UsersDep::class)->getMapWithProfile($memberIds);
        $invalidIds = \array_diff($memberIds, $existingUsers->keys()->toArray());
        self::throwIf(!empty($invalidIds), '用户不存在: ' . \implode(', ', $invalidIds));
        $this->ensureConfirmedContacts($currentUserId, $memberIds, '群聊成员必须是已确认的好友');

        $convDep = $this->dep(ChatConversationDep::class);
        $partDep = $this->dep(ChatParticipantDep::class);

        $conversation = $this->withTransaction(function () use ($convDep, $partDep, $currentUserId, $memberIds, $param) {
            $conversationId = $convDep->createConversation([
                'type'         => ChatEnum::CONVERSATION_GROUP,
                'name'         => $param['name'],
                'owner_id'     => $currentUserId,
                'member_count' => \count($memberIds) + 1,
                'is_del'       => CommonEnum::NO,
            ]);

            // 创建者为群主 + 其他成员为普通成员
            $participants = [['conversation_id' => $conversationId, 'user_id' => $currentUserId, 'role' => ChatEnum::ROLE_OWNER]];
            foreach ($memberIds as $memberId) {
                $participants[] = ['conversation_id' => $conversationId, 'user_id' => $memberId, 'role' => ChatEnum::ROLE_MEMBER];
            }
            $partDep->addBatch($participants);

            return $convDep->find($conversationId);
        });

        return self::success(['conversation' => $conversation->toArray()]);
    }

    /**
     * 会话列表（附带 Redis 未读计数，私聊填充对方用户名/头像）
     */
    public function conversationList($request): array
    {
        $currentUserId = $request->userId;
        $conversations = $this->dep(ChatConversationDep::class)->listByUser($currentUserId);

        // 批量获取未读计数
        $conversationIds = $conversations->pluck('id')->toArray();
        $unreadCounts = ChatService::getUnreadCounts($currentUserId, $conversationIds);

        // 私聊：批量查出对方的用户信息
        $privateConvIds = $conversations->where('type', ChatEnum::CONVERSATION_PRIVATE)->pluck('id')->toArray();
        $peerMap = $this->dep(ChatParticipantDep::class)->getPeersForPrivateConversations($privateConvIds, $currentUserId);

        $list = $conversations->map(function ($item) use ($unreadCounts, $peerMap) {
            $row = $item->toArray();
            $row['unread_count'] = $unreadCounts[$item->id] ?? 0;

            // 私聊：用对方的用户名和头像填充
            if ((int)$item->type === ChatEnum::CONVERSATION_PRIVATE && isset($peerMap[$item->id])) {
                $row['name']   = $peerMap[$item->id]['username'];
                $row['avatar'] = $peerMap[$item->id]['avatar'];
            }

            return $row;
        })->toArray();

        return self::success(['list' => $list]);
    }

    /**
     * 删除会话（仅对当前用户软删除，不影响其他参与者）
     */
    public function deleteConversation($request): array
    {
        $param = $this->validate($request, [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
        ]);
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        $affected = $this->dep(ChatParticipantDep::class)->softDelete($conversationId, $currentUserId);
        self::throwIf($affected === 0, '会话不存在或已删除');

        ChatService::deleteUnread($currentUserId, $conversationId);

        return self::success();
    }

    /**
     * 切换会话置顶
     */
    public function togglePin($request): array
    {
        $param = $this->validate($request, [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
        ]);
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $partDep = $this->dep(ChatParticipantDep::class);

        self::throwIf(!$partDep->isParticipant($conversationId, $currentUserId), '会话不存在');
        $partDep->togglePin($conversationId, $currentUserId);

        return self::success();
    }

    /**
     * 确保目标用户全部都是当前用户的已确认好友
     *
     * @param array<int> $targetUserIds
     */
    private function ensureConfirmedContacts(int $currentUserId, array $targetUserIds, string $message): void
    {
        if (empty($targetUserIds)) {
            return;
        }

        $confirmedUserIds = $this->dep(ChatContactDep::class)->getConfirmedContactUserIds($currentUserId);
        $invalidIds = \array_values(\array_diff($targetUserIds, $confirmedUserIds));
        self::throwIf(!empty($invalidIds), $message);
    }
}

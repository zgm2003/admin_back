<?php

namespace app\module\Chat;

use app\dep\Chat\ChatConversationDep;
use app\dep\Chat\ChatParticipantDep;
use app\dep\Chat\ChatMessageDep;
use app\dep\Chat\ChatContactDep;
use app\dep\User\UsersDep;
use app\enum\ChatEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Chat\ChatService;
use app\service\System\NotificationService;
use app\enum\NotificationEnum;
use app\validate\Chat\ChatValidate;

class ChatModule extends BaseModule
{
    protected ChatConversationDep $conversationDep;
    protected ChatParticipantDep $participantDep;
    protected ChatMessageDep $messageDep;
    protected ChatContactDep $contactDep;
    protected UsersDep $usersDep;

    public function __construct()
    {
        $this->conversationDep = $this->dep(ChatConversationDep::class);
        $this->participantDep = $this->dep(ChatParticipantDep::class);
        $this->messageDep = $this->dep(ChatMessageDep::class);
        $this->contactDep = $this->dep(ChatContactDep::class);
        $this->usersDep = $this->dep(UsersDep::class);
    }

    // ==================== 会话管理 ====================

    /**
     * 创建/获取私聊会话（幂等）
     * 如果两人之间已存在私聊会话则直接返回，否则创建新会话
     */
    public function createPrivate($request): array
    {
        $param = $this->validate($request, ChatValidate::createPrivate());
        $currentUserId = $request->userId;
        $targetUserId = (int)$param['user_id'];

        self::throwIf($currentUserId === $targetUserId, '不能与自己创建私聊');

        // 校验目标用户存在
        self::throwIf(!$this->usersDep->find($targetUserId), '用户不存在');

        // 校验必须是已确认的联系人才能私聊（对标微信逻辑）
        self::throwIf(!$this->contactDep->isConfirmedContact($currentUserId, $targetUserId), '对方不是你的联系人，请先添加联系人');

        // 事务内查找+创建，防止并发创建重复私聊会话
        $conversation = $this->withTransaction(function () use ($currentUserId, $targetUserId) {
            // 加锁查找已有私聊会话
            $existing = $this->conversationDep->findPrivateConversationForUpdate($currentUserId, $targetUserId);
            if ($existing) {
                // 恢复当前用户可能已软删除的参与者记录
                $this->participantDep->restoreDeleted($existing->id, $currentUserId);
                return $existing;
            }

            $conversationId = $this->conversationDep->createConversation([
                'type'         => ChatEnum::CONVERSATION_PRIVATE,
                'name'         => '',
                'owner_id'     => 0,
                'member_count' => 2,
                'is_del'       => CommonEnum::NO,
            ]);

            $this->participantDep->addBatch([
                ['conversation_id' => $conversationId, 'user_id' => $currentUserId, 'role' => ChatEnum::ROLE_MEMBER],
                ['conversation_id' => $conversationId, 'user_id' => $targetUserId, 'role' => ChatEnum::ROLE_MEMBER],
            ]);

            return $this->conversationDep->find($conversationId);
        });

        return self::success(['conversation' => $conversation->toArray()]);
    }

    /**
     * 创建群聊
     * 创建者自动成为群主，指定成员添加为普通成员
     */
    public function createGroup($request): array
    {
        $param = $this->validate($request, ChatValidate::createGroup());
        $currentUserId = $request->userId;
        $memberIds = array_map('intval', $param['user_ids']);

        // 去重并排除创建者自身
        $memberIds = array_values(array_unique(array_filter($memberIds, fn($id) => $id !== $currentUserId)));

        // 至少需要1名成员（加上创建者共2人）
        self::throwIf(\count($memberIds) < 1, '群聊至少需要选择1名好友');

        // 校验用户是否真实存在
        $existingUsers = $this->usersDep->getMapWithProfile($memberIds);
        $invalidIds = array_diff($memberIds, $existingUsers->keys()->toArray());
        self::throwIf(!empty($invalidIds), '用户不存在: ' . implode(', ', $invalidIds));

        $conversation = $this->withTransaction(function () use ($currentUserId, $memberIds, $param) {
            $conversationId = $this->conversationDep->createConversation([
                'type'         => ChatEnum::CONVERSATION_GROUP,
                'name'         => $param['name'],
                'owner_id'     => $currentUserId,
                'member_count' => count($memberIds) + 1,
                'is_del'       => CommonEnum::NO,
            ]);

            // 创建者为群主
            $participants = [
                ['conversation_id' => $conversationId, 'user_id' => $currentUserId, 'role' => ChatEnum::ROLE_OWNER],
            ];
            // 其他成员为普通成员
            foreach ($memberIds as $memberId) {
                $participants[] = ['conversation_id' => $conversationId, 'user_id' => $memberId, 'role' => ChatEnum::ROLE_MEMBER];
            }

            $this->participantDep->addBatch($participants);

            return $this->conversationDep->find($conversationId);
        });

        return self::success(['conversation' => $conversation->toArray()]);
    }

    /**
     * 会话列表
     * 返回用户参与的所有会话，附带 Redis 未读计数
     */
    public function conversationList($request): array
    {
        $currentUserId = $request->userId;

        $conversations = $this->conversationDep->listByUser($currentUserId);

        // 批量获取未读计数
        $conversationIds = $conversations->pluck('id')->toArray();
        $unreadCounts = ChatService::getUnreadCounts($currentUserId, $conversationIds);

        // 私聊会话：批量查出对方的用户信息（用户名、头像）
        $privateConvIds = $conversations->where('type', ChatEnum::CONVERSATION_PRIVATE)->pluck('id')->toArray();
        $peerMap = $this->participantDep->getPeersForPrivateConversations($privateConvIds, $currentUserId);

        // 合并未读计数 + 私聊对方信息
        $list = $conversations->map(function ($item) use ($unreadCounts, $peerMap) {
            $row = $item->toArray();
            $row['unread_count'] = $unreadCounts[$item->id] ?? 0;

            // 私聊：用对方的用户名和头像填充 name/avatar
            if ((int)$item->type === ChatEnum::CONVERSATION_PRIVATE && isset($peerMap[$item->id])) {
                $row['name'] = $peerMap[$item->id]['username'];
                $row['avatar'] = $peerMap[$item->id]['avatar'];
            }

            return $row;
        })->toArray();

        return self::success(['list' => $list]);
    }

    /**
     * 删除会话（仅对当前用户软删除）
     * 不影响其他参与者的会话数据
     */
    public function deleteConversation($request): array
    {
        $param = $this->validate($request, [
            'conversation_id' => \Respect\Validation\Validator::intVal()->positive()->setName('会话ID'),
        ]);
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        // 软删除参与者记录
        $affected = $this->participantDep->softDelete($conversationId, $currentUserId);
        self::throwIf($affected === 0, '会话不存在或已删除');

        // 清除 Redis 未读计数
        ChatService::deleteUnread($currentUserId, $conversationId);

        return self::success();
    }

    // ==================== 消息收发 ====================

    /**
     * 发送消息
     * 校验参与者权限、校验消息内容、写入消息、更新会话 last_message、递增 Redis 未读、WebSocket 推送
     */
    public function sendMessage($request): array
    {
        $param = $this->validate($request, ChatValidate::sendMessage());
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $type = (int)$param['type'];
        $content = $param['content'];
        $metaJson = $param['meta_json'] ?? null;

        // 校验参与者权限
        self::throwIf(
            !$this->participantDep->isParticipant($conversationId, $currentUserId),
            '无权操作',
            self::CODE_FORBIDDEN
        );

        // 文本消息校验：内容不能为空白
        if ($type === ChatEnum::MSG_TEXT) {
            self::throwIf(trim($content) === '', '消息内容不能为空');
        }

        // 写入消息
        $now = date('Y-m-d H:i:s');
        $messageId = $this->messageDep->addMessage([
            'conversation_id' => $conversationId,
            'sender_id'       => $currentUserId,
            'type'            => $type,
            'content'         => $content,
            'meta_json'       => $metaJson ? json_encode($metaJson, JSON_UNESCAPED_UNICODE) : null,
            'created_at'      => $now,
        ]);

        // 生成消息摘要
        $preview = match ($type) {
            ChatEnum::MSG_IMAGE  => '[图片]',
            ChatEnum::MSG_FILE   => '[文件]',
            ChatEnum::MSG_SYSTEM => $content,
            default              => $content,
        };

        // 更新会话 last_message
        $this->conversationDep->updateLastMessage($conversationId, $messageId, $now, $preview);

        // 获取活跃参与者
        $participants = $this->participantDep->getActiveParticipants($conversationId);
        $participantUserIds = $participants->pluck('user_id')->toArray();

        // 递增除发送者外所有参与者的未读计数
        $otherUserIds = array_values(array_filter($participantUserIds, fn($uid) => $uid != $currentUserId));
        if (!empty($otherUserIds)) {
            ChatService::incrementUnread($conversationId, $otherUserIds);
        }

        // 构建消息数据用于推送
        $user = $this->usersDep->findWithProfile($currentUserId);
        $senderInfo = $user ? [
            'id'       => $user->id,
            'username' => $user->username ?? '',
            'avatar'   => $user->avatar ?? '',
        ] : null;

        $messageData = [
            'id'              => $messageId,
            'conversation_id' => $conversationId,
            'sender_id'       => $currentUserId,
            'type'            => $type,
            'content'         => $content,
            'meta_json'       => $metaJson,
            'created_at'      => $now,
            'sender'          => $senderInfo,
        ];

        // WebSocket 推送（排除发送者）
        ChatService::pushMessage($conversationId, $participantUserIds, $messageData, $currentUserId);

        return self::success(['message' => $messageData]);
    }

    /**
     * 消息历史列表（游标分页）
     */
    public function messageList($request): array
    {
        $param = $this->validate($request, ChatValidate::messageList());
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $cursor = isset($param['cursor']) ? (int)$param['cursor'] : null;
        $pageSize = isset($param['page_size']) ? (int)$param['page_size'] : 20;

        // 校验参与者权限
        self::throwIf(
            !$this->participantDep->isParticipant($conversationId, $currentUserId),
            '无权操作',
            self::CODE_FORBIDDEN
        );

        // 游标分页查询
        $result = $this->messageDep->listByConversation($conversationId, $cursor, $pageSize);

        // 批量查询发送者信息
        $senderIds = $result['list']->pluck('sender_id')->unique()->filter()->values()->toArray();
        $usersMap = $this->usersDep->getMapWithProfile($senderIds);

        $list = $result['list']->map(function ($msg) use ($usersMap) {
            $row = $msg->toArray();
            $user = $usersMap[$msg->sender_id] ?? null;
            $row['sender'] = $user ? [
                'id'       => $user->id,
                'username' => $user->username ?? '',
                'avatar'   => $user->avatar ?? '',
            ] : null;
            return $row;
        });

        return self::cursorPaginate(
            $list,
            $result['next_cursor'],
            $result['has_more']
        );
    }

    /**
     * 标记已读
     * 重置 Redis 未读计数 + 更新 last_read_message_id + 推送已读回执
     */
    public function markRead($request): array
    {
        $param = $this->validate($request, ChatValidate::markRead());
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        // 校验参与者权限
        self::throwIf(
            !$this->participantDep->isParticipant($conversationId, $currentUserId),
            '无权操作',
            self::CODE_FORBIDDEN
        );

        // 重置 Redis 未读计数
        ChatService::resetUnread($currentUserId, $conversationId);

        // 获取会话的 last_message_id 作为已读位置
        $conversation = $this->conversationDep->find($conversationId);
        if ($conversation && $conversation->last_message_id > 0) {
            $this->participantDep->updateLastReadMessageId($conversationId, $currentUserId, $conversation->last_message_id);
        }

        // 推送已读回执给其他参与者
        $participants = $this->participantDep->getActiveParticipants($conversationId);
        $participantUserIds = $participants->pluck('user_id')->toArray();
        ChatService::pushReadReceipt($conversationId, $currentUserId, $participantUserIds);

        return self::success();
    }

    // ==================== 群聊管理 ====================

    /**
     * 校验当前用户是否为群主
     * @throws \app\exception\BusinessException
     */
    private function checkOwner(int $conversationId, int $userId): void
    {
        $participant = $this->participantDep->getParticipant($conversationId, $userId);
        self::throwIf(
            !$participant || $participant->role !== ChatEnum::ROLE_OWNER,
            '权限不足',
            self::CODE_FORBIDDEN
        );
    }

    /**
     * 发送系统消息并更新会话 last_message
     * @return array [messageId, participantUserIds] 消息ID和活跃参与者ID列表
     */
    private function sendSystemMessage(int $conversationId, string $content): array
    {
        $now = date('Y-m-d H:i:s');
        $messageId = $this->messageDep->addMessage([
            'conversation_id' => $conversationId,
            'sender_id'       => 0,
            'type'            => ChatEnum::MSG_SYSTEM,
            'content'         => $content,
            'meta_json'       => null,
            'created_at'      => $now,
        ]);

        $this->conversationDep->updateLastMessage($conversationId, $messageId, $now, $content);

        // 推送系统消息给所有活跃参与者
        $participants = $this->participantDep->getActiveParticipants($conversationId);
        $participantUserIds = $participants->pluck('user_id')->toArray();

        $messageData = [
            'id'              => $messageId,
            'conversation_id' => $conversationId,
            'sender_id'       => 0,
            'type'            => ChatEnum::MSG_SYSTEM,
            'content'         => $content,
            'meta_json'       => null,
            'created_at'      => $now,
        ];

        ChatService::pushMessage($conversationId, $participantUserIds, $messageData, 0);

        return [$messageId, $participantUserIds];
    }

    /**
     * 群聊详情
     * 返回群聊信息 + 活跃参与者列表
     */
    public function groupInfo($request): array
    {
        $param = $this->validate($request, [
            'conversation_id' => \Respect\Validation\Validator::intVal()->positive()->setName('会话ID'),
        ]);
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        $conversation = $this->conversationDep->find($conversationId);
        self::throwNotFound($conversation);
        self::throwIf($conversation->type !== ChatEnum::CONVERSATION_GROUP, '该会话不是群聊');

        // 校验当前用户是群成员
        self::throwIf(
            !$this->participantDep->isParticipant($conversationId, $currentUserId),
            '无权操作',
            self::CODE_FORBIDDEN
        );

        $participants = $this->participantDep->getActiveParticipantsWithProfile($conversationId);

        // 批量查询在线状态
        $memberUserIds = $participants->pluck('user_id')->toArray();
        $onlineMap = ChatService::getOnlineStatusBatch($memberUserIds);

        $participantList = $participants->map(function ($p) use ($onlineMap) {
            $row = $p->toArray();
            $row['is_online'] = $onlineMap[$p->user_id] ?? false;
            return $row;
        })->toArray();

        return self::success([
            'conversation'  => $conversation->toArray(),
            'participants'  => $participantList,
        ]);
    }

    /**
     * 修改群聊名称/公告
     * 仅群主可操作，修改后推送 WS_GROUP_UPDATE 给所有成员
     */
    public function groupUpdate($request): array
    {
        $param = $this->validate($request, ChatValidate::groupUpdate());
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        $conversation = $this->conversationDep->find($conversationId);
        self::throwNotFound($conversation);
        self::throwIf($conversation->type !== ChatEnum::CONVERSATION_GROUP, '该会话不是群聊');

        $this->checkOwner($conversationId, $currentUserId);

        // 构建更新数据
        $updateData = [];
        if (isset($param['name'])) {
            $updateData['name'] = $param['name'];
        }
        if (isset($param['announcement'])) {
            $updateData['announcement'] = $param['announcement'];
        }

        if (!empty($updateData)) {
            $this->conversationDep->update($conversationId, $updateData);
        }

        // 推送群更新通知给所有活跃参与者
        $participants = $this->participantDep->getActiveParticipants($conversationId);
        $participantUserIds = $participants->pluck('user_id')->toArray();
        ChatService::pushGroupUpdate($conversationId, $participantUserIds, $updateData);

        $updatedConversation = $this->conversationDep->find($conversationId);

        return self::success(['conversation' => $updatedConversation->toArray()]);
    }

    /**
     * 邀请成员加入群聊
     * 仅群主可操作，添加成员后发送系统消息
     */
    public function groupInvite($request): array
    {
        $param = $this->validate($request, ChatValidate::groupInvite());
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $userIds = array_map('intval', $param['user_ids']);

        $conversation = $this->conversationDep->find($conversationId);
        self::throwNotFound($conversation);
        self::throwIf($conversation->type !== ChatEnum::CONVERSATION_GROUP, '该会话不是群聊');

        $this->checkOwner($conversationId, $currentUserId);

        // 过滤已是活跃参与者的用户
        $existingParticipants = $this->participantDep->getActiveParticipants($conversationId);
        $existingUserIds = $existingParticipants->pluck('user_id')->toArray();
        $newUserIds = array_values(array_diff($userIds, $existingUserIds));

        self::throwIf(empty($newUserIds), '所选用户已在群聊中');

        // 区分：已有记录但非活跃（KICKED/LEFT）的用户 vs 完全新的用户
        $inactiveUserIds = $this->participantDep->getInactiveUserIds($conversationId, $newUserIds);
        $trulyNewUserIds = array_values(array_diff($newUserIds, $inactiveUserIds));

        // 恢复非活跃参与者
        if (!empty($inactiveUserIds)) {
            $this->participantDep->reactivateBatch($conversationId, $inactiveUserIds);
        }

        // 添加全新参与者
        if (!empty($trulyNewUserIds)) {
            $participants = [];
            foreach ($trulyNewUserIds as $uid) {
                $participants[] = [
                    'conversation_id' => $conversationId,
                    'user_id'         => $uid,
                    'role'            => ChatEnum::ROLE_MEMBER,
                ];
            }
            $this->participantDep->addBatch($participants);
        }

        // 更新成员数量
        $this->conversationDep->update($conversationId, [
            'member_count' => $conversation->member_count + \count($newUserIds),
        ]);

        // 发送系统消息（用用户名而非ID）
        $inviter = $this->usersDep->findWithProfile($currentUserId);
        $inviterName = $inviter->username ?? (string)$currentUserId;
        $newUsers = $this->usersDep->getMapWithProfile($newUserIds);
        $newUserNames = $newUsers->pluck('username')->implode('、') ?: implode('、', $newUserIds);
        [, $allParticipantUserIds] = $this->sendSystemMessage($conversationId, "{$inviterName} 邀请了 {$newUserNames} 加入群聊");
        ChatService::pushGroupUpdate($conversationId, $allParticipantUserIds, ['action' => 'invite', 'user_ids' => $newUserIds]);

        // 系统通知：通知每个被邀请的新成员
        $groupName = $conversation->name ?: '未命名群聊';
        foreach ($newUserIds as $uid) {
            NotificationService::send(
                $uid,
                '群聊邀请',
                "{$inviterName} 邀请你加入群聊「{$groupName}」",
                ['link' => '/chat']
            );
        }

        return self::success();
    }

    /**
     * 移除群聊成员
     * 仅群主可操作，不能移除自己
     */
    public function groupKick($request): array
    {
        $param = $this->validate($request, ChatValidate::groupKick());
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $targetUserId = (int)$param['user_id'];

        $conversation = $this->conversationDep->find($conversationId);
        self::throwNotFound($conversation);
        self::throwIf($conversation->type !== ChatEnum::CONVERSATION_GROUP, '该会话不是群聊');

        $this->checkOwner($conversationId, $currentUserId);
        self::throwIf($targetUserId === $currentUserId, '不能移除自己');

        // 校验目标用户是活跃参与者
        self::throwIf(
            !$this->participantDep->isParticipant($conversationId, $targetUserId),
            '该用户不在群聊中'
        );

        // 更新目标参与者状态为 KICKED
        $this->participantDep->updateStatus($conversationId, $targetUserId, ChatEnum::PARTICIPANT_KICKED);

        // 递减成员数量
        $this->conversationDep->update($conversationId, [
            'member_count' => max(0, $conversation->member_count - 1),
        ]);

        // 发送系统消息
        $targetUser = $this->usersDep->findWithProfile($targetUserId);
        $targetName = $targetUser->username ?? (string)$targetUserId;
        [, $remainingUserIds] = $this->sendSystemMessage($conversationId, "{$targetName} 被移出群聊");
        ChatService::pushGroupUpdate($conversationId, $remainingUserIds, ['action' => 'kick', 'user_id' => $targetUserId]);

        // 也通知被踢的人（让其前端清理状态）
        ChatService::pushGroupUpdate($conversationId, [$targetUserId], ['action' => 'kicked', 'user_id' => $targetUserId]);

        // 系统通知：通知被踢的人
        $groupName = $conversation->name ?: '未命名群聊';
        NotificationService::sendWarning(
            $targetUserId,
            '你已被移出群聊',
            "你已被移出群聊「{$groupName}」",
            [
                'level' => NotificationEnum::LEVEL_URGENT,
            ]
        );

        return self::success();
    }

    /**
     * 退出群聊
     * 群主不能退出（需先转让群主）
     */
    public function groupLeave($request): array
    {
        $param = $this->validate($request, ChatValidate::groupLeave());
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        $conversation = $this->conversationDep->find($conversationId);
        self::throwNotFound($conversation);
        self::throwIf($conversation->type !== ChatEnum::CONVERSATION_GROUP, '该会话不是群聊');

        // 群主不能直接退出
        $participant = $this->participantDep->getParticipant($conversationId, $currentUserId);
        self::throwIf(!$participant || $participant->status !== ChatEnum::PARTICIPANT_ACTIVE, '您不在该群聊中');
        self::throwIf($participant->role === ChatEnum::ROLE_OWNER, '群主不能退出群聊，请先转让群主');

        // 更新状态为 LEFT
        $this->participantDep->updateStatus($conversationId, $currentUserId, ChatEnum::PARTICIPANT_LEFT);

        // 递减成员数量
        $this->conversationDep->update($conversationId, [
            'member_count' => max(0, $conversation->member_count - 1),
        ]);

        // 发送系统消息
        $leaver = $this->usersDep->findWithProfile($currentUserId);
        $leaverName = $leaver->username ?? (string)$currentUserId;
        [, $remainingUserIds] = $this->sendSystemMessage($conversationId, "{$leaverName} 退出了群聊");

        // 推送群更新通知给剩余成员（刷新成员列表）
        ChatService::pushGroupUpdate($conversationId, $remainingUserIds, ['action' => 'leave', 'user_id' => $currentUserId]);

        return self::success();
    }

    /**
     * 转让群主
     * 事务中同时更新原群主和新群主的角色，以及会话 owner_id
     */
    public function groupTransfer($request): array
    {
        $param = $this->validate($request, ChatValidate::groupTransfer());
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $targetUserId = (int)$param['user_id'];

        $conversation = $this->conversationDep->find($conversationId);
        self::throwNotFound($conversation);
        self::throwIf($conversation->type !== ChatEnum::CONVERSATION_GROUP, '该会话不是群聊');

        $this->checkOwner($conversationId, $currentUserId);
        self::throwIf($targetUserId === $currentUserId, '不能转让给自己');

        // 校验目标用户是活跃参与者
        self::throwIf(
            !$this->participantDep->isParticipant($conversationId, $targetUserId),
            '目标用户不在群聊中'
        );

        // 事务：更新角色 + 更新 owner_id
        $this->withTransaction(function () use ($conversationId, $currentUserId, $targetUserId) {
            $this->participantDep->updateRole($conversationId, $currentUserId, ChatEnum::ROLE_MEMBER);
            $this->participantDep->updateRole($conversationId, $targetUserId, ChatEnum::ROLE_OWNER);
            $this->conversationDep->update($conversationId, ['owner_id' => $targetUserId]);
        });

        // 发送系统消息
        $targetUser = $this->usersDep->findWithProfile($targetUserId);
        $targetName = $targetUser->username ?? (string)$targetUserId;
        [, $allParticipantUserIds] = $this->sendSystemMessage($conversationId, "群主已转让给 {$targetName}");

        // 推送群更新通知给所有参与者
        ChatService::pushGroupUpdate($conversationId, $allParticipantUserIds, ['action' => 'transfer', 'new_owner_id' => $targetUserId]);

        // 系统通知：通知新群主
        $groupName = $conversation->name ?: '未命名群聊';
        NotificationService::sendSuccess(
            $targetUserId,
            '你已成为群主',
            "你已成为群聊「{$groupName}」的群主",
            [
                'level' => NotificationEnum::LEVEL_URGENT,
                'link' => '/chat',
            ]
        );

        return self::success();
    }

    // ==================== 联系人管理 ====================

    /**
     * 添加联系人
     * 创建双向 Contact 记录（A→B 和 B→A），初始状态为待确认
     * 不能添加自己，不能重复添加已存在的联系人
     */
    public function contactAdd($request): array
    {
        $param = $this->validate($request, ChatValidate::contactAdd());
        $currentUserId = $request->userId;
        $targetUserId = (int)$param['user_id'];

        self::throwIf($currentUserId === $targetUserId, '不能添加自己为联系人');

        // 校验目标用户存在
        self::throwIf(!$this->usersDep->find($targetUserId), '用户不存在');

        // 检查联系人是否已存在（任一方向，未删除）
        self::throwIf(
            $this->contactDep->contactExists($currentUserId, $targetUserId),
            '联系人已存在或请求待处理中'
        );

        // 检查是否有已软删除的记录（被拒绝/删除过），有则复活，否则新建
        try {
            if ($this->contactDep->existsDeletedBidirectional($currentUserId, $targetUserId)) {
                $this->contactDep->reactivateBidirectional($currentUserId, $targetUserId);
            } else {
                $this->contactDep->createBidirectional($currentUserId, $targetUserId);
            }
        } catch (\Throwable $e) {
            // 唯一键冲突（并发请求），视为已存在
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'uk_user_contact')) {
                self::throwIf(true, '联系人已存在或请求待处理中');
            }
            throw $e;
        }

        // 推送好友请求通知给对方
        ChatService::pushContactRequest($currentUserId, $targetUserId);

        // 系统通知持久化
        $currentUser = $this->usersDep->findWithProfile($currentUserId);
        $currentUserName = $currentUser->username ?? (string)$currentUserId;
        NotificationService::send(
            $targetUserId,
            '新的好友请求',
            "{$currentUserName} 请求添加你为好友",
            ['link' => '/chat']
        );

        return self::success();
    }

    /**
     * 确认联系人请求
     * 将双向 Contact 记录状态更新为已确认
     * 当前用户是被添加方，user_id 是发起添加的用户
     */
    public function contactConfirm($request): array
    {
        $param = $this->validate($request, ChatValidate::contactConfirm());
        $currentUserId = $request->userId;
        $fromUserId = (int)$param['user_id'];

        // 查找当前用户收到的联系人记录（current 的记录，对方是 fromUserId）
        $myRecord = $this->contactDep->getContact($currentUserId, $fromUserId);
        self::throwIf(!$myRecord, '联系人请求不存在');
        self::throwIf($myRecord->status !== ChatEnum::CONTACT_PENDING, '该联系人请求已处理');
        self::throwIf((int)$myRecord->is_initiator === 1, '不能确认自己发起的请求');

        // 双向确认
        $this->contactDep->confirmBidirectional($currentUserId, $fromUserId);

        // 通知发起方：好友请求已被确认
        ChatService::pushContactConfirmed($currentUserId, $fromUserId);

        // 系统通知持久化
        $currentUser = $this->usersDep->findWithProfile($currentUserId);
        $currentUserName = $currentUser->username ?? (string)$currentUserId;
        NotificationService::sendSuccess(
            $fromUserId,
            '好友请求已通过',
            "{$currentUserName} 接受了你的好友请求",
            [
                'link' => '/chat',
            ]
        );

        return self::success();
    }

    /**
     * 删除联系人
     * 软删除双向的 Contact 记录
     * 如果是已确认的联系人，同时清理私聊会话并通知对方
     */
    public function contactDelete($request): array
    {
        $param = $this->validate($request, ChatValidate::contactDelete());
        $currentUserId = $request->userId;
        $targetUserId = (int)$param['user_id'];

        // 验证联系人存在
        self::throwIf(
            !$this->contactDep->contactExists($currentUserId, $targetUserId),
            '联系人不存在'
        );

        // 查询当前用户侧的记录，判断场景
        $myRecord = $this->contactDep->getContact($currentUserId, $targetUserId);
        $isConfirmed = $myRecord && (int)$myRecord['status'] === ChatEnum::CONTACT_CONFIRMED;
        $isPendingReject = $myRecord
            && (int)$myRecord['status'] === ChatEnum::CONTACT_PENDING
            && (int)$myRecord['is_initiator'] === 0;

        // 双向软删除联系人
        $this->contactDep->softDeleteBidirectional($currentUserId, $targetUserId);

        $currentUser = $this->usersDep->findWithProfile($currentUserId);
        $currentUserName = $currentUser->username ?? (string)$currentUserId;

        if ($isPendingReject) {
            // 场景1：拒绝待确认请求
            ChatService::pushContactRejected($currentUserId, $targetUserId);

            NotificationService::sendWarning(
                $targetUserId,
                '好友请求被拒绝',
                "{$currentUserName} 拒绝了你的好友请求"
            );
        } elseif ($isConfirmed) {
            // 场景2：删除已确认的好友 → 同时清理私聊会话
            $privateConv = $this->conversationDep->findPrivateConversationAny($currentUserId, $targetUserId);
            $conversationId = null;

            if ($privateConv) {
                $conversationId = $privateConv->id;
                // 双方参与者都设为 LEFT，会话从双方列表消失
                $this->participantDep->updateStatus($conversationId, $currentUserId, ChatEnum::PARTICIPANT_LEFT);
                $this->participantDep->updateStatus($conversationId, $targetUserId, ChatEnum::PARTICIPANT_LEFT);
                // 清除双方的未读计数
                ChatService::deleteUnread($currentUserId, $conversationId);
                ChatService::deleteUnread($targetUserId, $conversationId);
            }

            // WebSocket 实时通知对方：联系人被删除 + 会话需清理
            ChatService::pushContactDeleted($currentUserId, $targetUserId, $conversationId);

            // 系统通知持久化
            NotificationService::sendWarning(
                $targetUserId,
                '联系人已被删除',
                "{$currentUserName} 将你从联系人中移除"
            );
        }

        return self::success();
    }

    /**
     * 联系人列表
     * 返回所有未删除的联系人（含待确认），附带在线状态
     */
    public function contactList($request): array
    {
        $currentUserId = $request->userId;

        $contacts = $this->contactDep->getAllContacts($currentUserId);

        // 批量查询在线状态
        $contactUserIds = $contacts->pluck('contact_user_id')->toArray();
        $onlineMap = ChatService::getOnlineStatusBatch($contactUserIds);

        $list = $contacts->map(function ($contact) use ($onlineMap) {
            $row = $contact->toArray();
            $row['is_online'] = $onlineMap[$contact->contact_user_id] ?? false;
            return $row;
        })->toArray();

        return self::success(['list' => $list]);
    }

    // ==================== 实时功能 ====================

    /**
     * 正在输入通知（仅私聊）
     * 向私聊会话中对方推送 chat_typing 消息
     */
    /**
     * 切换会话置顶
     */
    public function togglePin($request): array
    {
        $param = $this->validate($request, [
            'conversation_id' => \Respect\Validation\Validator::intVal()->positive()->setName('会话ID'),
        ]);
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        self::throwIf(
            !$this->participantDep->isParticipant($conversationId, $currentUserId),
            '会话不存在'
        );

        $this->participantDep->togglePin($conversationId, $currentUserId);

        return self::success();
    }

    public function typing($request): array
    {
        $param = $this->validate($request, ChatValidate::typing());
        $currentUserId = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        // 仅私聊支持输入状态
        $conversation = $this->conversationDep->find($conversationId);
        self::throwNotFound($conversation);
        if ($conversation->type !== ChatEnum::CONVERSATION_PRIVATE) {
            return self::success();
        }

        // 校验参与者权限
        self::throwIf(
            !$this->participantDep->isParticipant($conversationId, $currentUserId),
            '无权操作',
            self::CODE_FORBIDDEN
        );

        // 获取活跃参与者并推送 typing 状态
        $participants = $this->participantDep->getActiveParticipants($conversationId);
        $participantUserIds = $participants->pluck('user_id')->toArray();

        ChatService::pushTyping($conversationId, $currentUserId, $participantUserIds);

        return self::success();
    }

    /**
     * 查询用户在线状态
     * 仅允许查询自己的联系人或同会话参与者的在线状态
     */
    public function onlineStatus($request): array
    {
        $param = $this->validate($request, ChatValidate::onlineStatus());
        $currentUserId = $request->userId;
        $userIds = array_map('intval', $param['user_ids']);

        // 安全过滤：只返回当前用户的联系人的在线状态
        $contacts = $this->contactDep->getAllContacts($currentUserId);
        $contactUserIds = $contacts->pluck('contact_user_id')->toArray();
        $allowedIds = array_values(array_intersect($userIds, $contactUserIds));

        $statusMap = ChatService::getOnlineStatusBatch($allowedIds);

        return self::success(['online_status' => $statusMap]);
    }
}

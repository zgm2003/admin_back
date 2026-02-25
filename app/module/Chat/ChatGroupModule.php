<?php

namespace app\module\Chat;

use app\dep\Chat\ChatConversationDep;
use app\dep\Chat\ChatMessageDep;
use app\dep\Chat\ChatParticipantDep;
use app\dep\User\UsersDep;
use app\enum\ChatEnum;
use app\enum\NotificationEnum;
use app\module\BaseModule;
use app\service\Chat\ChatService;
use app\service\System\NotificationService;
use app\validate\Chat\ChatValidate;
use Respect\Validation\Validator as v;

/**
 * 群聊管理模块
 * 负责：群详情、修改群信息、邀请/移除成员、退出群聊、转让群主、设置管理员
 * 权限体系：群主 > 管理员 > 普通成员，所有敏感操作使用行锁防并发
 */
class ChatGroupModule extends BaseModule
{
    /**
     * 群聊详情（群信息 + 活跃成员列表 + 在线状态）
     */
    public function groupInfo($request): array
    {
        $param = $this->validate($request, [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
        ]);
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        $conversation = $this->dep(ChatConversationDep::class)->findGroupOrFail($conversationId);
        $partDep = $this->dep(ChatParticipantDep::class);

        self::throwIf(!$partDep->isParticipant($conversationId, $currentUserId), '无权操作', self::CODE_FORBIDDEN);

        $participants = $partDep->getActiveParticipantsWithProfile($conversationId);
        $memberUserIds = $participants->pluck('user_id')->toArray();
        $onlineMap = ChatService::getOnlineStatusBatch($memberUserIds);

        $participantList = $participants->map(function ($p) use ($onlineMap) {
            $row = $p->toArray();
            $row['is_online'] = $onlineMap[$p->user_id] ?? false;
            return $row;
        })->toArray();

        return self::success([
            'conversation' => $conversation->toArray(),
            'participants' => $participantList,
        ]);
    }

    /**
     * 修改群聊名称/公告（群主或管理员可操作）
     */
    public function groupUpdate($request): array
    {
        $param = $this->validate($request, ChatValidate::groupUpdate());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        $this->dep(ChatConversationDep::class)->findGroupOrFail($conversationId);
        $this->checkOwnerOrAdmin($conversationId, $currentUserId);

        $updateData = [];
        if (isset($param['name'])) {
            $updateData['name'] = $param['name'];
        }
        if (isset($param['announcement'])) {
            $updateData['announcement'] = $param['announcement'];
        }

        if (!empty($updateData)) {
            $this->dep(ChatConversationDep::class)->update($conversationId, $updateData);
        }

        // 推送群更新通知
        $participants = $this->dep(ChatParticipantDep::class)->getActiveParticipants($conversationId);
        ChatService::pushGroupUpdate($conversationId, $participants->pluck('user_id')->toArray(), $updateData);

        $updatedConversation = $this->dep(ChatConversationDep::class)->find($conversationId);

        return self::success(['conversation' => $updatedConversation->toArray()]);
    }

    /**
     * 邀请成员加入群聊（所有群成员可邀请）
     * 区分已有非活跃记录（恢复）和全新用户（新增），发送系统消息+通知
     */
    public function groupInvite($request): array
    {
        $param = $this->validate($request, ChatValidate::groupInvite());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $userIds = \array_map('intval', $param['user_ids']);

        $convDep = $this->dep(ChatConversationDep::class);
        $partDep = $this->dep(ChatParticipantDep::class);
        $conversation = $convDep->findGroupOrFail($conversationId);

        self::throwIf(!$partDep->isParticipant($conversationId, $currentUserId), '无权操作', self::CODE_FORBIDDEN);

        // 过滤已是活跃参与者的用户
        $existingUserIds = $partDep->getActiveParticipants($conversationId)->pluck('user_id')->toArray();
        $newUserIds = \array_values(\array_diff($userIds, $existingUserIds));
        self::throwIf(empty($newUserIds), '所选用户已在群聊中');

        // 区分：已有非活跃记录 vs 全新用户
        $inactiveUserIds = $partDep->getInactiveUserIds($conversationId, $newUserIds);
        $trulyNewUserIds = \array_values(\array_diff($newUserIds, $inactiveUserIds));

        $this->withTransaction(function () use ($convDep, $partDep, $conversationId, $inactiveUserIds, $trulyNewUserIds, $newUserIds) {
            if (!empty($inactiveUserIds)) {
                $partDep->reactivateBatch($conversationId, $inactiveUserIds);
            }
            if (!empty($trulyNewUserIds)) {
                $participants = [];
                foreach ($trulyNewUserIds as $uid) {
                    $participants[] = ['conversation_id' => $conversationId, 'user_id' => $uid, 'role' => ChatEnum::ROLE_MEMBER];
                }
                $partDep->addBatch($participants);
            }
            $convDep->increment($conversationId, 'member_count', \count($newUserIds));
        });

        // 发送系统消息
        $usersDep = $this->dep(UsersDep::class);
        $inviter = $usersDep->findWithProfile($currentUserId);
        $inviterName = $inviter->username ?? (string)$currentUserId;
        $newUsers = $usersDep->getMapWithProfile($newUserIds);
        $newUserNames = $newUsers->pluck('username')->implode('、') ?: \implode('、', $newUserIds);

        [, $allParticipantUserIds] = $this->sendSystemMessage($conversationId, "{$inviterName} 邀请了 {$newUserNames} 加入群聊");
        ChatService::pushGroupUpdate($conversationId, $allParticipantUserIds, ['action' => 'invite', 'user_ids' => $newUserIds]);

        // 系统通知每个被邀请的新成员
        $groupName = $conversation->name ?: '未命名群聊';
        foreach ($newUserIds as $uid) {
            NotificationService::send($uid, '群聊邀请', "{$inviterName} 邀请你加入群聊「{$groupName}」", ['link' => '/chat']);
        }

        return self::success();
    }

    /**
     * 移除群聊成员（群主可踢所有人，管理员只能踢普通成员）
     * 使用行锁防止并发冲突，发送系统消息+通知被踢者
     */
    public function groupKick($request): array
    {
        $param = $this->validate($request, ChatValidate::groupKick());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $targetUserId   = (int)$param['user_id'];

        $convDep = $this->dep(ChatConversationDep::class);
        $partDep = $this->dep(ChatParticipantDep::class);
        $conversation = $convDep->findGroupOrFail($conversationId);

        self::throwIf($targetUserId === $currentUserId, '不能移除自己');

        // 事务：行锁检查权限 + 更新状态 + 更新成员数量
        $this->withTransaction(function () use ($convDep, $partDep, $conversationId, $currentUserId, $targetUserId) {
            $current = $partDep->getParticipantForUpdate($conversationId, $currentUserId);
            self::throwNotFound($current, '你不在群聊中');
            self::throwIf($current->status !== ChatEnum::PARTICIPANT_ACTIVE, '你已退出群聊');

            $target = $partDep->getParticipantForUpdate($conversationId, $targetUserId);
            self::throwNotFound($target, '该用户不在群聊中');
            self::throwIf($target->status !== ChatEnum::PARTICIPANT_ACTIVE, '该用户已退出群聊');

            // 权限判断
            if ($current->role === ChatEnum::ROLE_OWNER) {
                // 群主可踢所有人
            } elseif ($current->role === ChatEnum::ROLE_ADMIN) {
                self::throwIf(
                    \in_array($target->role, [ChatEnum::ROLE_OWNER, ChatEnum::ROLE_ADMIN]),
                    '管理员不能移除群主或其他管理员', self::CODE_FORBIDDEN
                );
            } else {
                self::throwIf(true, '权限不足', self::CODE_FORBIDDEN);
            }

            $partDep->updateStatus($conversationId, $targetUserId, ChatEnum::PARTICIPANT_KICKED);
            $convDep->decrement($conversationId, 'member_count');
        });

        // 发送系统消息
        $targetUser = $this->dep(UsersDep::class)->findWithProfile($targetUserId);
        $targetName = $targetUser->username ?? (string)$targetUserId;
        [, $remainingUserIds] = $this->sendSystemMessage($conversationId, "{$targetName} 被移出群聊");
        ChatService::pushGroupUpdate($conversationId, $remainingUserIds, ['action' => 'kick', 'user_id' => $targetUserId]);

        // 通知被踢的人（让前端清理状态）
        ChatService::pushGroupUpdate($conversationId, [$targetUserId], ['action' => 'kicked', 'user_id' => $targetUserId]);

        // 系统通知
        $groupName = $conversation->name ?: '未命名群聊';
        NotificationService::sendWarning($targetUserId, '你已被移出群聊', "你已被移出群聊「{$groupName}」", ['level' => NotificationEnum::LEVEL_URGENT]);

        return self::success();
    }

    /**
     * 退出群聊（群主不能退出，需先转让群主）
     */
    public function groupLeave($request): array
    {
        $param = $this->validate($request, ChatValidate::groupLeave());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        $convDep = $this->dep(ChatConversationDep::class);
        $partDep = $this->dep(ChatParticipantDep::class);
        $conversation = $convDep->findGroupOrFail($conversationId);

        $participant = $partDep->getParticipant($conversationId, $currentUserId);
        self::throwUnless($participant && $participant->status === ChatEnum::PARTICIPANT_ACTIVE, '您不在该群聊中');
        self::throwIf($participant->role === ChatEnum::ROLE_OWNER, '群主不能退出群聊，请先转让群主');

        $partDep->updateStatus($conversationId, $currentUserId, ChatEnum::PARTICIPANT_LEFT);
        $convDep->decrement($conversationId, 'member_count');

        // 发送系统消息
        $leaver = $this->dep(UsersDep::class)->findWithProfile($currentUserId);
        $leaverName = $leaver->username ?? (string)$currentUserId;
        [, $remainingUserIds] = $this->sendSystemMessage($conversationId, "{$leaverName} 退出了群聊");
        ChatService::pushGroupUpdate($conversationId, $remainingUserIds, ['action' => 'leave', 'user_id' => $currentUserId]);

        return self::success();
    }

    /**
     * 转让群主（事务中同时更新双方角色 + 会话 owner_id）
     */
    public function groupTransfer($request): array
    {
        $param = $this->validate($request, ChatValidate::groupTransfer());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $targetUserId   = (int)$param['user_id'];

        $convDep = $this->dep(ChatConversationDep::class);
        $partDep = $this->dep(ChatParticipantDep::class);
        $conversation = $convDep->findGroupOrFail($conversationId);

        $this->checkOwner($conversationId, $currentUserId);
        self::throwIf($targetUserId === $currentUserId, '不能转让给自己');
        self::throwUnless($partDep->isParticipant($conversationId, $targetUserId), '目标用户不在群聊中');

        $this->withTransaction(function () use ($convDep, $partDep, $conversationId, $currentUserId, $targetUserId) {
            $partDep->updateRole($conversationId, $currentUserId, ChatEnum::ROLE_MEMBER);
            $partDep->updateRole($conversationId, $targetUserId, ChatEnum::ROLE_OWNER);
            $convDep->update($conversationId, ['owner_id' => $targetUserId]);
        });

        // 发送系统消息
        $targetUser = $this->dep(UsersDep::class)->findWithProfile($targetUserId);
        $targetName = $targetUser->username ?? (string)$targetUserId;
        [, $allParticipantUserIds] = $this->sendSystemMessage($conversationId, "群主已转让给 {$targetName}");
        ChatService::pushGroupUpdate($conversationId, $allParticipantUserIds, ['action' => 'transfer', 'new_owner_id' => $targetUserId]);

        // 系统通知新群主
        $groupName = $conversation->name ?: '未命名群聊';
        NotificationService::sendSuccess($targetUserId, '你已成为群主', "你已成为群聊「{$groupName}」的群主", ['level' => NotificationEnum::LEVEL_URGENT, 'link' => '/chat']);

        return self::success();
    }

    /**
     * 设置/取消管理员（仅群主可操作，使用行锁防并发）
     */
    public function setAdmin($request): array
    {
        $param = $this->validate($request, ChatValidate::setAdmin());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $targetUserId   = (int)$param['user_id'];
        $isAdmin        = (bool)$param['is_admin'];

        $this->dep(ChatConversationDep::class)->findGroupOrFail($conversationId);
        $this->checkOwner($conversationId, $currentUserId);
        self::throwIf($targetUserId === $currentUserId, '不能对自己操作');

        $newRole = $isAdmin ? ChatEnum::ROLE_ADMIN : ChatEnum::ROLE_MEMBER;
        $partDep = $this->dep(ChatParticipantDep::class);

        $this->withTransaction(function () use ($partDep, $conversationId, $targetUserId, $newRole) {
            $target = $partDep->getParticipantForUpdate($conversationId, $targetUserId);
            self::throwNotFound($target, '该用户不在群内');
            self::throwIf($target->status !== ChatEnum::PARTICIPANT_ACTIVE, '该用户已退出群聊');
            self::throwIf($target->role === ChatEnum::ROLE_OWNER, '不能对群主操作');
            $partDep->updateRole($conversationId, $targetUserId, $newRole);
        });

        // 发送系统消息
        $usersDep = $this->dep(UsersDep::class);
        $operatorName = $usersDep->findWithProfile($currentUserId)->username ?? (string)$currentUserId;
        $targetName   = $usersDep->findWithProfile($targetUserId)->username ?? (string)$targetUserId;
        $action = $isAdmin ? '设为管理员' : '取消了管理员';
        [, $allParticipantUserIds] = $this->sendSystemMessage($conversationId, "{$operatorName} {$action} {$targetName}");

        ChatService::pushGroupUpdate($conversationId, $allParticipantUserIds, ['action' => 'role_changed', 'user_id' => $targetUserId, 'role' => $newRole]);

        return self::success();
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 校验当前用户是否为群主
     */
    private function checkOwner(int $conversationId, int $userId): void
    {
        $participant = $this->dep(ChatParticipantDep::class)->getParticipant($conversationId, $userId);
        self::throwIf(!$participant || $participant->role !== ChatEnum::ROLE_OWNER, '权限不足', self::CODE_FORBIDDEN);
    }

    /**
     * 校验当前用户是否为群主或管理员
     */
    private function checkOwnerOrAdmin(int $conversationId, int $userId): void
    {
        $participant = $this->dep(ChatParticipantDep::class)->getParticipant($conversationId, $userId);
        self::throwIf(
            !$participant || !\in_array($participant->role, [ChatEnum::ROLE_OWNER, ChatEnum::ROLE_ADMIN]),
            '权限不足', self::CODE_FORBIDDEN
        );
    }

    /**
     * 发送系统消息并更新会话 last_message，推送给所有活跃参与者
     * @return array [messageId, participantUserIds]
     */
    private function sendSystemMessage(int $conversationId, string $content): array
    {
        $now = \date('Y-m-d H:i:s');
        $messageId = $this->dep(ChatMessageDep::class)->addMessage([
            'conversation_id' => $conversationId,
            'sender_id'       => 0,
            'type'            => ChatEnum::MSG_SYSTEM,
            'content'         => $content,
            'meta_json'       => null,
            'created_at'      => $now,
        ]);

        $this->dep(ChatConversationDep::class)->updateLastMessage($conversationId, $messageId, $now, $content);

        $participants = $this->dep(ChatParticipantDep::class)->getActiveParticipants($conversationId);
        $participantUserIds = $participants->pluck('user_id')->toArray();

        ChatService::pushMessage($conversationId, $participantUserIds, [
            'id'              => $messageId,
            'conversation_id' => $conversationId,
            'sender_id'       => 0,
            'type'            => ChatEnum::MSG_SYSTEM,
            'content'         => $content,
            'meta_json'       => null,
            'created_at'      => $now,
        ], 0);

        return [$messageId, $participantUserIds];
    }
}

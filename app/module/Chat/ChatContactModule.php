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
use app\service\System\NotificationService;
use app\validate\Chat\ChatValidate;

/**
 * 联系人管理 + 实时功能模块
 * 负责：联系人 CRUD（双向记录）、正在输入通知、在线状态查询
 * 联系人采用双向记录模式（A→B 和 B→A），支持并发安全的唯一键冲突处理
 */
class ChatContactModule extends BaseModule
{
    /**
     * 添加联系人（创建双向 Contact 记录，初始状态待确认）
     * 支持复活已软删除的记录（被拒绝/删除过），并发安全
     */
    public function contactAdd($request): array
    {
        $param = $this->validate($request, ChatValidate::contactAdd());
        $currentUserId = $request->userId;
        $targetUserId  = (int)$param['user_id'];

        self::throwIf($currentUserId === $targetUserId, '不能添加自己为联系人');
        $this->dep(UsersDep::class)->findOrFail($targetUserId);

        $contactDep = $this->dep(ChatContactDep::class);
        self::throwIf($contactDep->contactExists($currentUserId, $targetUserId), '联系人已存在或请求待处理中');

        // 复活已软删除的记录 或 新建
        try {
            if ($contactDep->existsDeletedBidirectional($currentUserId, $targetUserId)) {
                $contactDep->reactivateBidirectional($currentUserId, $targetUserId);
            } else {
                $contactDep->createBidirectional($currentUserId, $targetUserId);
            }
        } catch (\Throwable $e) {
            // 唯一键冲突（并发请求），视为已存在
            if (\str_contains($e->getMessage(), 'Duplicate entry') || \str_contains($e->getMessage(), 'uk_user_contact')) {
                self::throwIf(true, '联系人已存在或请求待处理中');
            }
            throw $e;
        }

        // 推送好友请求 + 系统通知
        ChatService::pushContactRequest($currentUserId, $targetUserId);
        $currentUser = $this->dep(UsersDep::class)->findWithProfile($currentUserId);
        $currentUserName = $currentUser->username ?? (string)$currentUserId;
        NotificationService::send($targetUserId, '新的好友请求', "{$currentUserName} 请求添加你为好友", ['link' => '/chat']);

        return self::success();
    }

    /**
     * 确认联系人请求（双向确认，当前用户是被添加方）
     */
    public function contactConfirm($request): array
    {
        $param = $this->validate($request, ChatValidate::contactConfirm());
        $currentUserId = $request->userId;
        $fromUserId    = (int)$param['user_id'];

        $contactDep = $this->dep(ChatContactDep::class);
        $myRecord = $contactDep->getContact($currentUserId, $fromUserId);
        self::throwUnless($myRecord, '联系人请求不存在');
        self::throwIf($myRecord->status !== ChatEnum::CONTACT_PENDING, '该联系人请求已处理');
        self::throwIf((int)$myRecord->is_initiator === CommonEnum::YES, '不能确认自己发起的请求');

        $contactDep->confirmBidirectional($currentUserId, $fromUserId);

        // 通知发起方
        ChatService::pushContactConfirmed($currentUserId, $fromUserId);
        $currentUser = $this->dep(UsersDep::class)->findWithProfile($currentUserId);
        $currentUserName = $currentUser->username ?? (string)$currentUserId;
        NotificationService::sendSuccess($fromUserId, '好友请求已通过', "{$currentUserName} 接受了你的好友请求", ['link' => '/chat']);

        return self::success();
    }

    /**
     * 删除联系人（双向软删除）
     * 已确认的联系人：同时清理私聊会话并通知对方
     * 待确认的请求（被添加方删除）：视为拒绝
     */
    public function contactDelete($request): array
    {
        $param = $this->validate($request, ChatValidate::contactDelete());
        $currentUserId = $request->userId;
        $targetUserId  = (int)$param['user_id'];

        $contactDep = $this->dep(ChatContactDep::class);
        self::throwIf(!$contactDep->contactExists($currentUserId, $targetUserId), '联系人不存在');

        $myRecord    = $contactDep->getContact($currentUserId, $targetUserId);
        $isConfirmed = $myRecord && (int)$myRecord->status === ChatEnum::CONTACT_CONFIRMED;
        $isPendingReject = $myRecord
            && (int)$myRecord->status === ChatEnum::CONTACT_PENDING
            && (int)$myRecord->is_initiator === CommonEnum::NO;

        // 双向软删除
        $contactDep->softDeleteBidirectional($currentUserId, $targetUserId);

        $currentUser = $this->dep(UsersDep::class)->findWithProfile($currentUserId);
        $currentUserName = $currentUser->username ?? (string)$currentUserId;

        if ($isPendingReject) {
            // 拒绝待确认请求
            ChatService::pushContactRejected($currentUserId, $targetUserId);
            NotificationService::sendWarning($targetUserId, '好友请求被拒绝', "{$currentUserName} 拒绝了你的好友请求");
        } elseif ($isConfirmed) {
            // 删除已确认好友 → 同时清理私聊会话
            $this->cleanupPrivateConversation($currentUserId, $targetUserId);
            ChatService::pushContactDeleted($currentUserId, $targetUserId, $this->getPrivateConversationId($currentUserId, $targetUserId));
            NotificationService::sendWarning($targetUserId, '联系人已被删除', "{$currentUserName} 将你从联系人中移除");
        }

        return self::success();
    }

    /**
     * 联系人列表（含在线状态）
     */
    public function contactList($request): array
    {
        $currentUserId = $request->userId;
        $contacts = $this->dep(ChatContactDep::class)->getAllContacts($currentUserId);

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
     * 正在输入通知（仅私聊，推送给对方）
     */
    public function typing($request): array
    {
        $param = $this->validate($request, ChatValidate::typing());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];

        $conversation = $this->dep(ChatConversationDep::class)->findOrFail($conversationId);
        if ($conversation->type !== ChatEnum::CONVERSATION_PRIVATE) {
            return self::success();
        }

        $partDep = $this->dep(ChatParticipantDep::class);
        self::throwIf(!$partDep->isParticipant($conversationId, $currentUserId), '无权操作', self::CODE_FORBIDDEN);

        $participants = $partDep->getActiveParticipants($conversationId);
        ChatService::pushTyping($conversationId, $currentUserId, $participants->pluck('user_id')->toArray());

        return self::success();
    }

    /**
     * 查询用户在线状态（安全过滤：仅返回联系人的在线状态）
     */
    public function onlineStatus($request): array
    {
        $param = $this->validate($request, ChatValidate::onlineStatus());
        $currentUserId = $request->userId;
        $userIds = \array_map('intval', $param['user_ids']);

        // 安全过滤：只返回当前用户的联系人
        $contactUserIds = $this->dep(ChatContactDep::class)->getAllContacts($currentUserId)->pluck('contact_user_id')->toArray();
        $allowedIds = \array_values(\array_intersect($userIds, $contactUserIds));

        return self::success(['online_status' => ChatService::getOnlineStatusBatch($allowedIds)]);
    }

    // ==================== 私有辅助 ====================

    /**
     * 清理私聊会话（双方参与者设为 LEFT + 清除未读）
     */
    private function cleanupPrivateConversation(int $userA, int $userB): void
    {
        $privateConv = $this->dep(ChatConversationDep::class)->findPrivateConversationAny($userA, $userB);
        if (!$privateConv) {
            return;
        }

        $partDep = $this->dep(ChatParticipantDep::class);
        $partDep->updateStatus($privateConv->id, $userA, ChatEnum::PARTICIPANT_LEFT);
        $partDep->updateStatus($privateConv->id, $userB, ChatEnum::PARTICIPANT_LEFT);
        ChatService::deleteUnread($userA, $privateConv->id);
        ChatService::deleteUnread($userB, $privateConv->id);
    }

    /**
     * 获取私聊会话 ID（用于推送通知）
     */
    private function getPrivateConversationId(int $userA, int $userB): ?int
    {
        $conv = $this->dep(ChatConversationDep::class)->findPrivateConversationAny($userA, $userB);
        return $conv?->id;
    }
}

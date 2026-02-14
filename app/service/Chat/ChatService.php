<?php

namespace app\service\Chat;

use app\enum\ChatEnum;
use GatewayWorker\Lib\Gateway;
use support\Log;
use support\Redis;

/**
 * 聊天服务层
 * 封装 Redis 未读计数和 WebSocket 推送逻辑
 */
class ChatService
{
    /**
     * Redis 未读计数键前缀
     */
    const UNREAD_KEY_PREFIX = 'chat:unread:';

    /**
     * Gateway 注册地址
     */
    const GATEWAY_REGISTER_ADDRESS = '127.0.0.1:1236';

    // ==================== Redis 未读计数 ====================

    /**
     * 递增未读计数
     * 为指定会话中的多个用户递增未读消息数
     *
     * @param int $conversationId 会话ID
     * @param array $userIds 需要递增未读的用户ID列表
     */
    public static function incrementUnread(int $conversationId, array $userIds): void
    {
        foreach ($userIds as $userId) {
            Redis::hIncrBy(self::UNREAD_KEY_PREFIX . $userId, (string)$conversationId, 1);
        }
    }

    /**
     * 重置未读计数
     * 将指定用户在指定会话的未读数重置为 0
     *
     * @param int $userId 用户ID
     * @param int $conversationId 会话ID
     */
    public static function resetUnread(int $userId, int $conversationId): void
    {
        Redis::hDel(self::UNREAD_KEY_PREFIX . $userId, (string)$conversationId);
    }

    /**
     * 批量获取未读计数
     * 获取指定用户在多个会话中的未读消息数
     *
     * @param int $userId 用户ID
     * @param array $conversationIds 会话ID列表
     * @return array 关联数组 [conversation_id => unread_count]
     */
    public static function getUnreadCounts(int $userId, array $conversationIds): array
    {
        if (empty($conversationIds)) {
            return [];
        }

        $fields = array_map('strval', $conversationIds);
        $values = Redis::hMGet(self::UNREAD_KEY_PREFIX . $userId, $fields);

        $result = [];
        foreach ($conversationIds as $i => $conversationId) {
            $result[$conversationId] = (int)($values[$fields[$i]] ?? 0);
        }

        return $result;
    }

    /**
     * 删除未读计数
     * 用户删除会话时清除对应的未读计数
     *
     * @param int $userId 用户ID
     * @param int $conversationId 会话ID
     */
    public static function deleteUnread(int $userId, int $conversationId): void
    {
        Redis::hDel(self::UNREAD_KEY_PREFIX . $userId, (string)$conversationId);
    }

    // ==================== WebSocket 推送 ====================

    /**
     * 推送新消息
     * 向会话中除发送者外的所有参与者推送消息
     *
     * @param int $conversationId 会话ID
     * @param array $participantUserIds 会话参与者用户ID列表
     * @param array $messageData 消息数据
     * @param int $excludeUserId 排除的用户ID（发送者）
     */
    public static function pushMessage(int $conversationId, array $participantUserIds, array $messageData, int $excludeUserId): void
    {
        $payload = json_encode([
            'type' => ChatEnum::WS_CHAT_MESSAGE,
            'data' => [
                'conversation_id' => $conversationId,
                'message' => $messageData,
            ]
        ], JSON_UNESCAPED_UNICODE);

        foreach ($participantUserIds as $userId) {
            if ($userId == $excludeUserId) {
                continue;
            }
            self::safeSendToUid($userId, $payload);
        }
    }

    /**
     * 推送正在输入状态
     *
     * @param int $conversationId 会话ID
     * @param int $userId 正在输入的用户ID
     * @param array $participantUserIds 会话参与者用户ID列表
     */
    public static function pushTyping(int $conversationId, int $userId, array $participantUserIds): void
    {
        $payload = json_encode([
            'type' => ChatEnum::WS_CHAT_TYPING,
            'data' => [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
            ]
        ], JSON_UNESCAPED_UNICODE);

        foreach ($participantUserIds as $uid) {
            if ($uid == $userId) {
                continue;
            }
            self::safeSendToUid($uid, $payload);
        }
    }

    /**
     * 推送已读回执
     *
     * @param int $conversationId 会话ID
     * @param int $userId 标记已读的用户ID
     * @param array $participantUserIds 会话参与者用户ID列表
     */
    public static function pushReadReceipt(int $conversationId, int $userId, array $participantUserIds): void
    {
        $payload = json_encode([
            'type' => ChatEnum::WS_CHAT_READ,
            'data' => [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
            ]
        ], JSON_UNESCAPED_UNICODE);

        foreach ($participantUserIds as $uid) {
            if ($uid == $userId) {
                continue;
            }
            self::safeSendToUid($uid, $payload);
        }
    }

    /**
     * 推送在线状态变更
     *
     * @param int $userId 状态变更的用户ID
     * @param bool $isOnline 是否在线
     * @param array $contactUserIds 需要通知的联系人用户ID列表
     */
    public static function pushOnlineStatus(int $userId, bool $isOnline, array $contactUserIds): void
    {
        $payload = json_encode([
            'type' => ChatEnum::WS_CHAT_ONLINE,
            'data' => [
                'user_id' => $userId,
                'is_online' => $isOnline,
            ]
        ], JSON_UNESCAPED_UNICODE);

        foreach ($contactUserIds as $uid) {
            self::safeSendToUid($uid, $payload);
        }
    }

    /**
     * 推送群聊更新通知（群名/公告变更）
     *
     * @param int $conversationId 会话ID
     * @param array $participantUserIds 会话参与者用户ID列表
     * @param array $updateData 更新的数据
     */
    public static function pushGroupUpdate(int $conversationId, array $participantUserIds, array $updateData): void
    {
        $payload = json_encode([
            'type' => ChatEnum::WS_GROUP_UPDATE,
            'data' => [
                'conversation_id' => $conversationId,
                'update' => $updateData,
            ]
        ], JSON_UNESCAPED_UNICODE);

        foreach ($participantUserIds as $userId) {
            self::safeSendToUid($userId, $payload);
        }
    }

    /**
     * 推送联系人请求通知
     *
     * @param int $fromUserId 发起添加的用户ID
     * @param int $toUserId 被添加的用户ID
     */
    public static function pushContactRequest(int $fromUserId, int $toUserId): void
    {
        $payload = json_encode([
            'type' => ChatEnum::WS_CONTACT_REQUEST,
            'data' => [
                'from_user_id' => $fromUserId,
            ]
        ], JSON_UNESCAPED_UNICODE);

        self::safeSendToUid($toUserId, $payload);
    }

    /**
     * 推送联系人请求被拒绝通知
     *
     * @param int $rejectedByUserId 拒绝操作的用户ID
     * @param int $initiatorUserId 被拒绝的发起方用户ID
     */
    public static function pushContactRejected(int $rejectedByUserId, int $initiatorUserId): void
    {
        $payload = json_encode([
            'type' => ChatEnum::WS_CONTACT_REJECTED,
            'data' => [
                'rejected_by' => $rejectedByUserId,
            ]
        ], JSON_UNESCAPED_UNICODE);

        self::safeSendToUid($initiatorUserId, $payload);
    }

    /**
     * 推送联系人请求已确认通知
     *
     * @param int $confirmedByUserId 确认操作的用户ID
     * @param int $initiatorUserId 发起方用户ID
     */
    public static function pushContactConfirmed(int $confirmedByUserId, int $initiatorUserId): void
    {
        $payload = json_encode([
            'type' => ChatEnum::WS_CONTACT_CONFIRMED,
            'data' => [
                'confirmed_by' => $confirmedByUserId,
            ]
        ], JSON_UNESCAPED_UNICODE);

        self::safeSendToUid($initiatorUserId, $payload);
    }

    /**
     * 推送联系人被删除通知
     *
     * @param int $deletedByUserId 执行删除的用户ID
     * @param int $targetUserId 被删除的用户ID
     * @param int|null $conversationId 关联的私聊会话ID（如有）
     */
    public static function pushContactDeleted(int $deletedByUserId, int $targetUserId, ?int $conversationId = null): void
    {
        $payload = json_encode([
            'type' => ChatEnum::WS_CONTACT_DELETED,
            'data' => [
                'deleted_by' => $deletedByUserId,
                'conversation_id' => $conversationId,
            ]
        ], JSON_UNESCAPED_UNICODE);

        self::safeSendToUid($targetUserId, $payload);
    }

    /**
     * 安全发送 WebSocket 消息到指定用户
     * 推送失败仅记录警告日志，不抛出异常
     *
     * @param int $userId 目标用户ID
     * @param string $message JSON 消息内容
     */
    private static function safeSendToUid(int $userId, string $message): void
    {
        try {
            Gateway::$registerAddress = self::GATEWAY_REGISTER_ADDRESS;
            Gateway::sendToUid((string)$userId, $message);
        } catch (\Throwable $e) {
            Log::warning("[ChatService] WebSocket 推送失败: userId={$userId}, " . $e->getMessage());
        }
    }

    // ==================== 在线状态查询 ====================

    /**
     * 批量查询用户在线状态
     *
     * @param array $userIds 用户ID列表
     * @return array 关联数组 [userId => bool]
     */
    public static function getOnlineStatusBatch(array $userIds): array
    {
        $result = [];
        if (empty($userIds)) {
            return $result;
        }

        Gateway::$registerAddress = self::GATEWAY_REGISTER_ADDRESS;
        foreach ($userIds as $uid) {
            try {
                $result[$uid] = Gateway::isUidOnline((string)$uid);
            } catch (\Throwable $e) {
                $result[$uid] = false;
            }
        }

        return $result;
    }
}

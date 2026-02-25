<?php

namespace app\module\Chat;

use app\dep\Chat\ChatConversationDep;
use app\dep\Chat\ChatMessageDep;
use app\dep\Chat\ChatParticipantDep;
use app\dep\User\UsersDep;
use app\enum\ChatEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Chat\ChatService;
use app\validate\Chat\ChatValidate;

/**
 * 聊天消息模块
 * 负责：发送消息、消息历史（游标分页）、标记已读、撤回消息
 * 所有操作均校验参与者权限
 */
class ChatMessageModule extends BaseModule
{
    /** @var int 撤回消息时间限制（秒） */
    private const RECALL_TIME_LIMIT = 120;

    /**
     * 发送消息
     * 校验参与者权限 → 写入消息 → 更新 last_message → 递增未读 → WebSocket 推送
     */
    public function sendMessage($request): array
    {
        $param = $this->validate($request, ChatValidate::sendMessage());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $type           = (int)$param['type'];
        $content        = $param['content'];
        $metaJson       = $param['meta_json'] ?? null;

        $partDep = $this->dep(ChatParticipantDep::class);
        self::throwIf(!$partDep->isParticipant($conversationId, $currentUserId), '无权操作', self::CODE_FORBIDDEN);

        // 文本消息不能为空白
        if ($type === ChatEnum::MSG_TEXT) {
            self::throwIf(\trim($content) === '', '消息内容不能为空');
        }

        // 写入消息
        $now = \date('Y-m-d H:i:s');
        $msgDep = $this->dep(ChatMessageDep::class);
        $messageId = $msgDep->addMessage([
            'conversation_id' => $conversationId,
            'sender_id'       => $currentUserId,
            'type'            => $type,
            'content'         => $content,
            'meta_json'       => $metaJson ? \json_encode($metaJson, JSON_UNESCAPED_UNICODE) : null,
            'created_at'      => $now,
        ]);

        // 消息摘要
        $preview = match ($type) {
            ChatEnum::MSG_IMAGE  => '[图片]',
            ChatEnum::MSG_FILE   => '[文件]',
            ChatEnum::MSG_SYSTEM => $content,
            default              => $content,
        };

        // 更新会话 last_message
        $this->dep(ChatConversationDep::class)->updateLastMessage($conversationId, $messageId, $now, $preview);

        // 递增除发送者外所有参与者的未读计数
        $participants = $partDep->getActiveParticipants($conversationId);
        $participantUserIds = $participants->pluck('user_id')->toArray();
        $otherUserIds = \array_values(\array_filter($participantUserIds, fn($uid) => $uid != $currentUserId));
        if (!empty($otherUserIds)) {
            ChatService::incrementUnread($conversationId, $otherUserIds);
        }

        // 构建消息数据用于推送
        $user = $this->dep(UsersDep::class)->findWithProfile($currentUserId);
        $messageData = [
            'id'              => $messageId,
            'conversation_id' => $conversationId,
            'sender_id'       => $currentUserId,
            'type'            => $type,
            'content'         => $content,
            'meta_json'       => $metaJson,
            'created_at'      => $now,
            'sender'          => $user ? [
                'id'       => $user->id,
                'username' => $user->username ?? '',
                'avatar'   => $user->avatar ?? '',
            ] : null,
        ];

        ChatService::pushMessage($conversationId, $participantUserIds, $messageData, $currentUserId);

        return self::success(['message' => $messageData]);
    }

    /**
     * 消息历史列表（游标分页，批量查询发送者信息）
     */
    public function messageList($request): array
    {
        $param = $this->validate($request, ChatValidate::messageList());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $cursor         = isset($param['cursor']) ? (int)$param['cursor'] : null;
        $pageSize       = isset($param['page_size']) ? (int)$param['page_size'] : 20;

        self::throwIf(
            !$this->dep(ChatParticipantDep::class)->isParticipant($conversationId, $currentUserId),
            '无权操作', self::CODE_FORBIDDEN
        );

        $result = $this->dep(ChatMessageDep::class)->listByConversation($conversationId, $cursor, $pageSize);

        // 批量查询发送者信息
        $senderIds = $result['list']->pluck('sender_id')->unique()->filter()->values()->toArray();
        $usersMap = $this->dep(UsersDep::class)->getMapWithProfile($senderIds);

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

        return self::cursorPaginate($list, $result['next_cursor'], $result['has_more']);
    }

    /**
     * 标记已读（重置 Redis 未读 + 更新 last_read_message_id + 推送已读回执）
     */
    public function markRead($request): array
    {
        $param = $this->validate($request, ChatValidate::markRead());
        $currentUserId  = $request->userId;
        $conversationId = (int)$param['conversation_id'];
        $partDep = $this->dep(ChatParticipantDep::class);

        self::throwIf(!$partDep->isParticipant($conversationId, $currentUserId), '无权操作', self::CODE_FORBIDDEN);

        ChatService::resetUnread($currentUserId, $conversationId);

        // 更新已读位置
        $conversation = $this->dep(ChatConversationDep::class)->find($conversationId);
        if ($conversation && $conversation->last_message_id > 0) {
            $partDep->updateLastReadMessageId($conversationId, $currentUserId, $conversation->last_message_id);
        }

        // 推送已读回执给其他参与者
        $participants = $partDep->getActiveParticipants($conversationId);
        ChatService::pushReadReceipt($conversationId, $currentUserId, $participants->pluck('user_id')->toArray());

        return self::success();
    }

    /**
     * 撤回消息
     * 自己的消息：私聊 2 分钟限制；群聊群主/管理员无时间限制，普通成员 2 分钟
     * 别人的消息：仅群聊群主/管理员可撤回（管理员不能撤回群主/其他管理员的）
     */
    public function recallMessage($request): array
    {
        $param = $this->validate($request, ChatValidate::recallMessage());
        $currentUserId = $request->userId;
        $messageId = (int)$param['message_id'];

        $msgDep  = $this->dep(ChatMessageDep::class);
        $convDep = $this->dep(ChatConversationDep::class);
        $partDep = $this->dep(ChatParticipantDep::class);

        $message = $msgDep->findOrFail($messageId);
        self::throwIf($message->is_del === CommonEnum::YES, '消息已被删除');

        $conversation = $convDep->findOrFail($message->conversation_id);
        $isSelf    = $message->sender_id === $currentUserId;
        $isGroup   = $conversation->type === ChatEnum::CONVERSATION_GROUP;

        if ($isSelf) {
            $this->recallOwnMessage($partDep, $message, $isGroup, $currentUserId);
        } else {
            self::throwIf(!$isGroup, '只能撤回自己的消息', self::CODE_FORBIDDEN);
            $this->recallOtherMessage($partDep, $message, $currentUserId);
        }

        // 将消息标记为撤回：改为系统消息类型 + 撤回提示内容（不软删除，保留在消息流中）
        $recallerName = $this->dep(UsersDep::class)->findWithProfile($currentUserId)->username ?? (string)$currentUserId;
        $msgDep->update($messageId, [
            'type'    => ChatEnum::MSG_SYSTEM,
            'content' => "{$recallerName} 撤回了一条消息",
        ]);

        // 推送撤回通知
        $participants = $partDep->getActiveParticipants($message->conversation_id);
        ChatService::pushMessageRecall($message->conversation_id, $messageId, $participants->pluck('user_id')->toArray());

        return self::success();
    }

    /**
     * 撤回自己的消息（私聊 2 分钟限制；群聊群主/管理员无限制，普通成员 2 分钟）
     */
    private function recallOwnMessage(ChatParticipantDep $partDep, $message, bool $isGroup, int $currentUserId): void
    {
        if ($isGroup) {
            $needTimeCheck = false;
            $this->withTransaction(function () use ($partDep, $message, $currentUserId, &$needTimeCheck) {
                $participant = $partDep->getParticipantForUpdate($message->conversation_id, $currentUserId);
                self::throwNotFound($participant, '你不在群聊中');
                self::throwIf($participant->status !== ChatEnum::PARTICIPANT_ACTIVE, '你已退出群聊');

                if (!\in_array($participant->role, [ChatEnum::ROLE_OWNER, ChatEnum::ROLE_ADMIN])) {
                    $needTimeCheck = true;
                }
            });

            if ($needTimeCheck) {
                $this->checkRecallTimeLimit($message);
            }
        } else {
            // 私聊：2 分钟限制
            $this->checkRecallTimeLimit($message);
        }
    }

    /**
     * 撤回别人的消息（仅群聊群主/管理员，管理员不能撤回群主/其他管理员的）
     */
    private function recallOtherMessage(ChatParticipantDep $partDep, $message, int $currentUserId): void
    {
        $this->withTransaction(function () use ($partDep, $message, $currentUserId) {
            $current = $partDep->getParticipantForUpdate($message->conversation_id, $currentUserId);
            self::throwNotFound($current, '你不在群聊中');
            self::throwIf($current->status !== ChatEnum::PARTICIPANT_ACTIVE, '你已退出群聊');

            $sender = $partDep->getParticipantForUpdate($message->conversation_id, $message->sender_id);

            if ($current->role === ChatEnum::ROLE_OWNER) {
                // 群主可撤回所有人的消息
            } elseif ($current->role === ChatEnum::ROLE_ADMIN) {
                self::throwIf(
                    $sender && \in_array($sender->role, [ChatEnum::ROLE_OWNER, ChatEnum::ROLE_ADMIN]),
                    '管理员不能撤回群主或其他管理员的消息', self::CODE_FORBIDDEN
                );
            } else {
                self::throwIf(true, '权限不足', self::CODE_FORBIDDEN);
            }
        });
    }

    /**
     * 校验撤回时间限制（2 分钟）
     */
    private function checkRecallTimeLimit($message): void
    {
        self::throwIf(\time() - \strtotime($message->created_at) > self::RECALL_TIME_LIMIT, '消息发送超过2分钟，无法撤回');
    }
}

<?php

namespace app\enum;

class ChatEnum
{
    // 会话类型
    const CONVERSATION_PRIVATE = 1;  // 私聊
    const CONVERSATION_GROUP = 2;    // 群聊

    // 消息类型
    const MSG_TEXT = 1;     // 文本
    const MSG_IMAGE = 2;    // 图片
    const MSG_FILE = 3;     // 文件
    const MSG_SYSTEM = 4;   // 系统消息

    // 参与者角色
    const ROLE_OWNER = 1;   // 群主
    const ROLE_ADMIN = 2;   // 管理员
    const ROLE_MEMBER = 3;  // 普通成员

    // 参与者状态
    const PARTICIPANT_ACTIVE = 1;   // 正常
    const PARTICIPANT_LEFT = 2;     // 已退出
    const PARTICIPANT_KICKED = 3;   // 被移除

    // 联系人状态
    const CONTACT_PENDING = 1;    // 待确认
    const CONTACT_CONFIRMED = 2;  // 已确认

    // WebSocket 消息类型
    const WS_CHAT_MESSAGE = 'chat_message';
    const WS_CHAT_TYPING = 'chat_typing';
    const WS_CHAT_READ = 'chat_read';
    const WS_CHAT_ONLINE = 'chat_online';
    const WS_GROUP_UPDATE = 'chat_group_update';
    const WS_CONTACT_REQUEST = 'chat_contact_request';
    const WS_CONTACT_REJECTED = 'chat_contact_rejected';
    const WS_CONTACT_CONFIRMED = 'chat_contact_confirmed';

    // 会话类型映射
    public static $conversationTypeArr = [
        self::CONVERSATION_PRIVATE => '私聊',
        self::CONVERSATION_GROUP => '群聊',
    ];

    // 消息类型映射
    public static $msgTypeArr = [
        self::MSG_TEXT => '文本',
        self::MSG_IMAGE => '图片',
        self::MSG_FILE => '文件',
        self::MSG_SYSTEM => '系统消息',
    ];

    // 参与者角色映射
    public static $roleArr = [
        self::ROLE_OWNER => '群主',
        self::ROLE_ADMIN => '管理员',
        self::ROLE_MEMBER => '成员',
    ];

    // 参与者状态映射
    public static $participantStatusArr = [
        self::PARTICIPANT_ACTIVE => '正常',
        self::PARTICIPANT_LEFT => '已退出',
        self::PARTICIPANT_KICKED => '被移除',
    ];

    // 联系人状态映射
    public static $contactStatusArr = [
        self::CONTACT_PENDING => '待确认',
        self::CONTACT_CONFIRMED => '已确认',
    ];
}

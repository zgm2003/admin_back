<?php

namespace app\validate\Chat;

use app\enum\ChatEnum;
use Respect\Validation\Validator as v;

class ChatValidate
{
    /**
     * 创建私聊会话
     */
    public static function createPrivate(): array
    {
        return [
            'user_id' => v::intVal()->positive()->setName('目标用户ID'),
        ];
    }

    /**
     * 创建群聊会话
     */
    public static function createGroup(): array
    {
        return [
            'name'       => v::stringType()->length(1, 100)->setName('群聊名称'),
            'user_ids' => v::arrayType()->length(1, null)->setName('成员列表'),
        ];
    }

    /**
     * 发送消息
     */
    public static function sendMessage(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
            'type'            => v::intVal()->in([ChatEnum::MSG_TEXT, ChatEnum::MSG_IMAGE, ChatEnum::MSG_FILE])->setName('消息类型'),
            'content'         => v::stringType()->notEmpty()->length(1, 5000)->setName('消息内容'),
            'meta_json'       => v::optional(v::arrayType())->setName('附加信息'),
        ];
    }

    /**
     * 消息历史列表（游标分页）
     */
    public static function messageList(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
            'cursor'          => v::optional(v::intVal()->positive())->setName('游标'),
            'page_size'       => v::optional(v::intVal()->between(1, 50))->setName('每页数量'),
        ];
    }

    /**
     * 标记已读
     */
    public static function markRead(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
        ];
    }

    /**
     * 撤回消息
     */
    public static function recallMessage(): array
    {
        return [
            'message_id' => v::intVal()->positive()->setName('消息ID'),
        ];
    }

    /**
     * 修改群聊信息（名称/公告）
     */
    public static function groupUpdate(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
            'name'            => v::optional(v::stringType()->length(1, 100))->setName('群聊名称'),
            'announcement'    => v::optional(v::stringType())->setName('群公告'),
        ];
    }

    /**
     * 邀请成员加入群聊
     */
    public static function groupInvite(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
            'user_ids'        => v::arrayType()->length(1, null)->setName('用户列表'),
        ];
    }

    /**
     * 移除群聊成员
     */
    public static function groupKick(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
            'user_id'         => v::intVal()->positive()->setName('用户ID'),
        ];
    }

    /**
     * 退出群聊
     */
    public static function groupLeave(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
        ];
    }

    /**
     * 转让群主
     */
    public static function groupTransfer(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
            'user_id'         => v::intVal()->positive()->setName('用户ID'),
        ];
    }

    /**
     * 设置/取消管理员
     */
    public static function setAdmin(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
            'user_id'         => v::intVal()->positive()->setName('用户ID'),
            'is_admin'        => v::boolVal()->setName('是否为管理员'),
        ];
    }

    /**
     * 添加联系人
     */
    public static function contactAdd(): array
    {
        return [
            'user_id' => v::intVal()->positive()->setName('用户ID'),
        ];
    }

    /**
     * 确认联系人请求
     */
    public static function contactConfirm(): array
    {
        return [
            'user_id' => v::intVal()->positive()->setName('用户ID'),
        ];
    }

    /**
     * 删除联系人
     */
    public static function contactDelete(): array
    {
        return [
            'user_id' => v::intVal()->positive()->setName('用户ID'),
        ];
    }

    /**
     * 联系人列表
     */
    public static function contactList(): array
    {
        return [];
    }

    /**
     * 正在输入通知
     */
    public static function typing(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
        ];
    }

    /**
     * 查询用户在线状态
     */
    public static function onlineStatus(): array
    {
        return [
            'user_ids' => v::arrayType()->length(1, 100)->setName('用户列表'),
        ];
    }
}

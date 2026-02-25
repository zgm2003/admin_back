<?php

namespace app\controller\Chat;

use app\controller\Controller;
use app\module\Chat\ChatContactModule;
use app\module\Chat\ChatConversationModule;
use app\module\Chat\ChatGroupModule;
use app\module\Chat\ChatMessageModule;
use support\Request;

/**
 * 聊天控制器
 * 路由分发到 4 个职责模块：会话、消息、群管理、联系人+实时
 */
class ChatController extends Controller
{
    // ==================== 会话管理 ====================

    /** 会话列表 */
    public function conversationList(Request $request) { return $this->run([ChatConversationModule::class, 'conversationList'], $request); }

    /** 创建/获取私聊会话 */
    public function createPrivate(Request $request) { return $this->run([ChatConversationModule::class, 'createPrivate'], $request); }

    /** 创建群聊 */
    public function createGroup(Request $request) { return $this->run([ChatConversationModule::class, 'createGroup'], $request); }

    /** 删除会话 */
    public function deleteConversation(Request $request) { return $this->run([ChatConversationModule::class, 'deleteConversation'], $request); }

    /** 切换会话置顶 */
    public function togglePin(Request $request) { return $this->run([ChatConversationModule::class, 'togglePin'], $request); }

    // ==================== 消息收发 ====================

    /** 发送消息 */
    public function sendMessage(Request $request) { return $this->run([ChatMessageModule::class, 'sendMessage'], $request); }

    /** 消息历史 */
    public function messageList(Request $request) { return $this->run([ChatMessageModule::class, 'messageList'], $request); }

    /** 标记已读 */
    public function markRead(Request $request) { return $this->run([ChatMessageModule::class, 'markRead'], $request); }

    /** 撤回消息 */
    public function recallMessage(Request $request) { return $this->run([ChatMessageModule::class, 'recallMessage'], $request); }

    // ==================== 群聊管理 ====================

    /** 群聊详情 */
    public function groupInfo(Request $request) { return $this->run([ChatGroupModule::class, 'groupInfo'], $request); }

    /** 修改群名称/公告 */
    public function groupUpdate(Request $request) { return $this->run([ChatGroupModule::class, 'groupUpdate'], $request); }

    /** 邀请成员 */
    public function groupInvite(Request $request) { return $this->run([ChatGroupModule::class, 'groupInvite'], $request); }

    /** 移除成员 */
    public function groupKick(Request $request) { return $this->run([ChatGroupModule::class, 'groupKick'], $request); }

    /** 退出群聊 */
    public function groupLeave(Request $request) { return $this->run([ChatGroupModule::class, 'groupLeave'], $request); }

    /** 转让群主 */
    public function groupTransfer(Request $request) { return $this->run([ChatGroupModule::class, 'groupTransfer'], $request); }

    /** 设置/取消管理员 */
    public function setAdmin(Request $request) { return $this->run([ChatGroupModule::class, 'setAdmin'], $request); }

    // ==================== 联系人 + 实时 ====================

    /** 联系人列表 */
    public function contactList(Request $request) { return $this->run([ChatContactModule::class, 'contactList'], $request); }

    /** 添加联系人 */
    public function contactAdd(Request $request) { return $this->run([ChatContactModule::class, 'contactAdd'], $request); }

    /** 确认联系人请求 */
    public function contactConfirm(Request $request) { return $this->run([ChatContactModule::class, 'contactConfirm'], $request); }

    /** 删除联系人 */
    public function contactDelete(Request $request) { return $this->run([ChatContactModule::class, 'contactDelete'], $request); }

    /** 正在输入通知 */
    public function typing(Request $request) { return $this->run([ChatContactModule::class, 'typing'], $request); }

    /** 查询用户在线状态 */
    public function onlineStatus(Request $request) { return $this->run([ChatContactModule::class, 'onlineStatus'], $request); }
}

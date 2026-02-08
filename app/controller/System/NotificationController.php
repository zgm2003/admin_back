<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\NotificationModule;
use support\Request;

class NotificationController extends Controller
{
    /** 初始化（字典） */
    public function init(Request $request) { return $this->run([NotificationModule::class, 'init'], $request); }

    /** 获取通知列表（普通分页，独立页面用） */
    public function list(Request $request) { return $this->run([NotificationModule::class, 'list'], $request); }

    /** 获取通知列表（游标分页，Popover用） */
    public function listCursor(Request $request) { return $this->run([NotificationModule::class, 'listCursor'], $request); }

    /** 获取未读数量 */
    public function unreadCount(Request $request) { return $this->run([NotificationModule::class, 'unreadCount'], $request); }

    /** 标记已读 */
    public function read(Request $request) { return $this->run([NotificationModule::class, 'read'], $request); }

    /** 删除通知 */
    public function del(Request $request) { return $this->run([NotificationModule::class, 'del'], $request); }
}

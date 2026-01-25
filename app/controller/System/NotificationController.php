<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\NotificationModule;
use support\Request;

class NotificationController extends Controller
{
    /** 获取通知列表 */
    public function list(Request $request) { return $this->run([NotificationModule::class, 'list'], $request); }

    /** 获取未读数量 */
    public function unreadCount(Request $request) { return $this->run([NotificationModule::class, 'unreadCount'], $request); }

    /** 标记已读（传id标记单条，不传标记全部） */
    public function read(Request $request) { return $this->run([NotificationModule::class, 'read'], $request); }

    /** 删除通知 */
    public function del(Request $request) { return $this->run([NotificationModule::class, 'del'], $request); }
}

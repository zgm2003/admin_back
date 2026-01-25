<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\NotificationTaskModule;
use support\Request;

/**
 * 通知任务管理控制器
 */
class NotificationTaskController extends Controller
{
    public function init(Request $request) { return $this->run([NotificationTaskModule::class, 'init'], $request); }
    public function statusCount(Request $request) { return $this->run([NotificationTaskModule::class, 'statusCount'], $request); }
    public function list(Request $request) { return $this->run([NotificationTaskModule::class, 'list'], $request); }
    public function add(Request $request) { return $this->run([NotificationTaskModule::class, 'add'], $request); }
    public function del(Request $request) { return $this->run([NotificationTaskModule::class, 'del'], $request); }
    public function cancel(Request $request) { return $this->run([NotificationTaskModule::class, 'cancel'], $request); }
}

<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\PayNotifyLogModule;
use support\Request;

class PayNotifyLogController extends Controller
{
    /** @Permission("pay_notify_view") */
    public function init(Request $request) { return $this->run([PayNotifyLogModule::class, 'init'], $request); }
    /** @Permission("pay_notify_view") */
    public function list(Request $request) { return $this->run([PayNotifyLogModule::class, 'list'], $request); }
    /** @Permission("pay_notify_view") */
    public function detail(Request $request) { return $this->run([PayNotifyLogModule::class, 'detail'], $request); }
}

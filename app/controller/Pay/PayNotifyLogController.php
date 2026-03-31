<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\PayNotifyLogModule;
use support\Request;

class PayNotifyLogController extends Controller
{
    public function init(Request $request) { return $this->run([PayNotifyLogModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([PayNotifyLogModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([PayNotifyLogModule::class, 'detail'], $request); }
}

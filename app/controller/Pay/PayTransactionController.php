<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\PayTransactionModule;
use support\Request;

/**
 * 支付流水管理
 */
class PayTransactionController extends Controller
{
    public function init(Request $request) { return $this->run([PayTransactionModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([PayTransactionModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([PayTransactionModule::class, 'detail'], $request); }
}

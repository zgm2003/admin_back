<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\PayRefundModule;
use support\Request;

/**
 * 退款管理
 */
class PayRefundController extends Controller
{
    public function init(Request $request) { return $this->run([PayRefundModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([PayRefundModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([PayRefundModule::class, 'detail'], $request); }
    /** @OperationLog("申请退款") @Permission("pay_refund_apply") */
    public function apply(Request $request) { return $this->run([PayRefundModule::class, 'apply'], $request); }
}

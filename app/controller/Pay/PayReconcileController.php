<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\PayReconcileModule;
use support\Request;

/**
 * 对账管理
 */
class PayReconcileController extends Controller
{
    public function init(Request $request) { return $this->run([PayReconcileModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([PayReconcileModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([PayReconcileModule::class, 'detail'], $request); }
    /** @OperationLog("重试对账任务") @Permission("pay_reconcile_retry") */
    public function retry(Request $request) { return $this->run([PayReconcileModule::class, 'retry'], $request); }
    /** @Permission("pay_reconcile_download") */
    public function download(Request $request)
    {
        return $this->run([PayReconcileModule::class, 'download'], $request);
    }
}

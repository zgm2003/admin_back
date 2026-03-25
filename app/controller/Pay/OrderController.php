<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\OrderModule;
use support\Request;

/**
 * 统一订单管理
 */
class OrderController extends Controller
{
    public function init(Request $request) { return $this->run([OrderModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([OrderModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([OrderModule::class, 'detail'], $request); }
    public function statusCount(Request $request) { return $this->run([OrderModule::class, 'statusCount'], $request); }
    /** @OperationLog("关闭订单") @Permission("pay_order_edit") */
    public function close(Request $request) { return $this->run([OrderModule::class, 'close'], $request); }
    /** @OperationLog("备注订单") @Permission("pay_order_edit") */
    public function remark(Request $request) { return $this->run([OrderModule::class, 'remark'], $request); }

    // ==================== App 端接口 ====================
    public function recharge(Request $request)
    {
        return $this->run([OrderModule::class, 'recharge'], $request);
    }

    public function createPay(Request $request)
    {
        return $this->run([OrderModule::class, 'createPay'], $request);
    }

    public function queryResult(Request $request)
    {
        return $this->run([OrderModule::class, 'queryResult'], $request);
    }

    public function orderDetail(Request $request)
    {
        return $this->run([OrderModule::class, 'orderDetail'], $request);
    }

    public function walletInfo(Request $request)
    {
        return $this->run([OrderModule::class, 'walletInfo'], $request);
    }

    public function walletBills(Request $request)
    {
        return $this->run([OrderModule::class, 'walletBills'], $request);
    }
}

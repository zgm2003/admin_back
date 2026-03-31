<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\OrderAdminModule;
use app\module\Pay\RechargeModule;
use app\module\Pay\WalletQueryModule;
use support\Request;

/**
 * 统一订单管理
 */
class OrderController extends Controller
{
    public function init(Request $request) { return $this->run([OrderAdminModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([OrderAdminModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([OrderAdminModule::class, 'detail'], $request); }
    public function statusCount(Request $request) { return $this->run([OrderAdminModule::class, 'statusCount'], $request); }
    /** @OperationLog("关闭订单") @Permission("pay_order_edit") */
    public function close(Request $request) { return $this->run([OrderAdminModule::class, 'close'], $request); }
    /** @OperationLog("备注订单") @Permission("pay_order_edit") */
    public function remark(Request $request) { return $this->run([OrderAdminModule::class, 'remark'], $request); }

    // ==================== 用户侧接口 ====================
    public function recharge(Request $request)
    {
        return $this->run([RechargeModule::class, 'recharge'], $request);
    }

    public function createPay(Request $request)
    {
        return $this->run([RechargeModule::class, 'createPay'], $request);
    }

    public function cancelOrder(Request $request)
    {
        return $this->run([RechargeModule::class, 'cancelOrder'], $request);
    }

    public function queryResult(Request $request)
    {
        return $this->run([RechargeModule::class, 'queryResult'], $request);
    }

    public function myOrders(Request $request)
    {
        return $this->run([RechargeModule::class, 'myOrders'], $request);
    }

    public function orderDetail(Request $request)
    {
        return $this->run([RechargeModule::class, 'orderDetail'], $request);
    }

    public function walletInfo(Request $request)
    {
        return $this->run([WalletQueryModule::class, 'walletInfo'], $request);
    }

    public function walletBills(Request $request)
    {
        return $this->run([WalletQueryModule::class, 'walletBills'], $request);
    }
}

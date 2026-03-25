<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\PayChannelModule;
use support\Request;

/**
 * 支付渠道管理
 */
class PayChannelController extends Controller
{
    public function init(Request $request) { return $this->run([PayChannelModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([PayChannelModule::class, 'list'], $request); }
    /** @OperationLog("新增支付渠道") @Permission("pay_channel_add") */
    public function add(Request $request) { return $this->run([PayChannelModule::class, 'add'], $request); }
    /** @OperationLog("编辑支付渠道") @Permission("pay_channel_edit") */
    public function edit(Request $request) { return $this->run([PayChannelModule::class, 'edit'], $request); }
    /** @OperationLog("删除支付渠道") @Permission("pay_channel_del") */
    public function del(Request $request) { return $this->run([PayChannelModule::class, 'del'], $request); }
    /** @OperationLog("切换支付渠道状态") @Permission("pay_channel_status") */
    public function status(Request $request) { return $this->run([PayChannelModule::class, 'status'], $request); }
}

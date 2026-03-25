<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\UserWalletModule;
use support\Request;

/**
 * 钱包管理
 */
class UserWalletController extends Controller
{
    public function init(Request $request) { return $this->run([UserWalletModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([UserWalletModule::class, 'list'], $request); }
    public function transactions(Request $request) { return $this->run([UserWalletModule::class, 'transactions'], $request); }
    /** @OperationLog("调整钱包余额") @Permission("pay_wallet_adjust") */
    public function adjust(Request $request) { return $this->run([UserWalletModule::class, 'adjust'], $request); }
}

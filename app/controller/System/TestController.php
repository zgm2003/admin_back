<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\TestModule;
use support\Request;

class TestController extends Controller
{
    public function init(Request $request) { return $this->run([TestModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([TestModule::class, 'list'], $request); }

    /** @OperationLog("Test新增") @Permission("system_test_add") */
    public function add(Request $request) { return $this->run([TestModule::class, 'add'], $request); }

    /** @OperationLog("Test编辑") @Permission("system_test_edit") */
    public function edit(Request $request) { return $this->run([TestModule::class, 'edit'], $request); }

    /** @OperationLog("Test删除") @Permission("system_test_del") */
    public function del(Request $request) { return $this->run([TestModule::class, 'del'], $request); }
}
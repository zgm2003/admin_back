<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\OperationLogModule;
use support\Request;

class OperationLogController extends Controller
{
    public function init(Request $request) { return $this->run([OperationLogModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([OperationLogModule::class, 'list'], $request); }

    /** @Permission("operationLog.del") */
    public function del(Request $request) { return $this->run([OperationLogModule::class, 'del'], $request); }
}

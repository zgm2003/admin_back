<?php

namespace app\controller\DevTools;

use app\controller\Controller;
use app\module\DevTools\ExportTaskModule;
use support\Request;

/**
 * 导出任务管理控制器
 */
class ExportTaskController extends Controller
{
    public function init(Request $request) { return $this->run([ExportTaskModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([ExportTaskModule::class, 'list'], $request); }
    public function del(Request $request) { return $this->run([ExportTaskModule::class, 'del'], $request); }
    public function batchDel(Request $request) { return $this->run([ExportTaskModule::class, 'batchDel'], $request); }
}

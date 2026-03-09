<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\ExportTaskModule;
use support\Request;

/**
 * 导出任务管理控制器
 */
class ExportTaskController extends Controller
{
    public function statusCount(Request $request) { return $this->run([ExportTaskModule::class, 'statusCount'], $request); }
    public function list(Request $request) { return $this->run([ExportTaskModule::class, 'list'], $request); }
    public function del(Request $request) { return $this->run([ExportTaskModule::class, 'del'], $request); }
}

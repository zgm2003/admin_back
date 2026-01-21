<?php

namespace app\controller\DevTools;

use app\controller\Controller;
use app\module\DevTools\CronTaskModule;
use support\Request;

/**
 * 定时任务控制器
 */
class CronTaskController extends Controller
{
    public function init(Request $request) { return $this->run([CronTaskModule::class, 'init'], $request); }
    
    public function list(Request $request) { return $this->run([CronTaskModule::class, 'list'], $request); }
    
    /** @OperationLog("新增定时任务") @Permission("devTools_cronTask_add") */
    public function add(Request $request) { return $this->run([CronTaskModule::class, 'add'], $request); }
    
    /** @OperationLog("编辑定时任务") @Permission("devTools_cronTask_edit") */
    public function edit(Request $request) { return $this->run([CronTaskModule::class, 'edit'], $request); }
    
    /** @OperationLog("删除定时任务") @Permission("devTools_cronTask_del") */
    public function del(Request $request) { return $this->run([CronTaskModule::class, 'del'], $request); }
    
    /** @OperationLog("定时任务状态切换") @Permission("devTools_cronTask_status") */
    public function status(Request $request) { return $this->run([CronTaskModule::class, 'status'], $request); }
    
    /** @Permission("devTools_cronTask_logs") */
    public function logs(Request $request) { return $this->run([CronTaskModule::class, 'logs'], $request); }
}

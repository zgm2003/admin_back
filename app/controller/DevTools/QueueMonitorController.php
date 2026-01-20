<?php

namespace app\controller\DevTools;

use app\controller\Controller;
use app\module\DevTools\QueueMonitorModule;
use support\Request;

/**
 * 队列监控控制器
 */
class QueueMonitorController extends Controller
{
    public function list(Request $request) { return $this->run([QueueMonitorModule::class, 'list'], $request); }
    public function failedList(Request $request) { return $this->run([QueueMonitorModule::class, 'failedList'], $request); }
    public function retry(Request $request) { return $this->run([QueueMonitorModule::class, 'retry'], $request); }
    public function clear(Request $request) { return $this->run([QueueMonitorModule::class, 'clear'], $request); }
    public function clearFailed(Request $request) { return $this->run([QueueMonitorModule::class, 'clearFailed'], $request); }
}

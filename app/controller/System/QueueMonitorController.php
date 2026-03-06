<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\QueueMonitorModule;
use support\Request;

class QueueMonitorController extends Controller
{
    public function list(Request $request) { return $this->run([QueueMonitorModule::class, 'list'], $request); }
    public function failedList(Request $request) { return $this->run([QueueMonitorModule::class, 'failedList'], $request); }
    public function retry(Request $request) { return $this->run([QueueMonitorModule::class, 'retry'], $request); }
    public function clear(Request $request) { return $this->run([QueueMonitorModule::class, 'clear'], $request); }
    public function clearFailed(Request $request) { return $this->run([QueueMonitorModule::class, 'clearFailed'], $request); }
}

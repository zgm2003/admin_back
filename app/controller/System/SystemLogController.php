<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\SystemLogModule;
use support\Request;

class SystemLogController extends Controller
{
    public function init(Request $request) { return $this->run([SystemLogModule::class, 'init'], $request); }

    /** @Permission("system_log_files") */
    public function files(Request $request) { return $this->run([SystemLogModule::class, 'files'], $request); }

    /** @Permission("system_log_content") */
    public function content(Request $request) { return $this->run([SystemLogModule::class, 'content'], $request); }
}

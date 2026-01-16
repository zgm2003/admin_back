<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiAgentModule;
use support\Request;

class AiAgentController extends Controller
{
    public function init(Request $request) { return $this->run([AiAgentModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([AiAgentModule::class, 'list'], $request); }
    public function add(Request $request) { return $this->run([AiAgentModule::class, 'add'], $request); }
    public function edit(Request $request) { return $this->run([AiAgentModule::class, 'edit'], $request); }
    public function del(Request $request) { return $this->run([AiAgentModule::class, 'del'], $request); }
    public function status(Request $request) { return $this->run([AiAgentModule::class, 'status'], $request); }
}

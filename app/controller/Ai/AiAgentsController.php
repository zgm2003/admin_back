<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiAgentsModule;
use support\Request;

class AiAgentsController extends Controller
{
    public function init(Request $request) { return $this->run([AiAgentsModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([AiAgentsModule::class, 'list'], $request); }
    public function add(Request $request) { return $this->run([AiAgentsModule::class, 'add'], $request); }
    public function edit(Request $request) { return $this->run([AiAgentsModule::class, 'edit'], $request); }
    public function del(Request $request) { return $this->run([AiAgentsModule::class, 'del'], $request); }
    public function status(Request $request) { return $this->run([AiAgentsModule::class, 'status'], $request); }
}

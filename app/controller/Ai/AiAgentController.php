<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiAgentModule;
use support\Request;

class AiAgentController extends Controller
{
    public function init(Request $request)
    {
        $this->run([AiAgentModule::class, 'init'], $request);
        return $this->response();
    }

    public function list(Request $request)
    {
        $this->run([AiAgentModule::class, 'list'], $request);
        return $this->response();
    }

    public function add(Request $request) { $this->run([AiAgentModule::class, 'add'], $request); return $this->response(); }
    public function edit(Request $request) { $this->run([AiAgentModule::class, 'edit'], $request); return $this->response(); }
    public function del(Request $request) { $this->run([AiAgentModule::class, 'del'], $request); return $this->response(); }
    public function status(Request $request) { $this->run([AiAgentModule::class, 'status'], $request); return $this->response(); }
}

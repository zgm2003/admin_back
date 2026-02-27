<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiModelsModule;
use support\Request;

class AiModelsController extends Controller
{
    public function init(Request $request) { return $this->run([AiModelsModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([AiModelsModule::class, 'list'], $request); }
    public function add(Request $request) { return $this->run([AiModelsModule::class, 'add'], $request); }
    public function edit(Request $request) { return $this->run([AiModelsModule::class, 'edit'], $request); }
    public function del(Request $request) { return $this->run([AiModelsModule::class, 'del'], $request); }
    public function status(Request $request) { return $this->run([AiModelsModule::class, 'status'], $request); }
}

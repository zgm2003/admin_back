<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiModelModule;
use support\Request;

class AiModelController extends Controller
{
    public function init(Request $request) { return $this->run([AiModelModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([AiModelModule::class, 'list'], $request); }
    public function add(Request $request) { return $this->run([AiModelModule::class, 'add'], $request); }
    public function edit(Request $request) { return $this->run([AiModelModule::class, 'edit'], $request); }
    public function del(Request $request) { return $this->run([AiModelModule::class, 'del'], $request); }
    public function status(Request $request) { return $this->run([AiModelModule::class, 'status'], $request); }
}

<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiMessageModule;
use support\Request;

class AiMessageController extends Controller
{
    public function list(Request $request) { return $this->run([AiMessageModule::class, 'list'], $request); }
    public function del(Request $request) { return $this->run([AiMessageModule::class, 'del'], $request); }
    public function feedback(Request $request) { return $this->run([AiMessageModule::class, 'feedback'], $request); }
}

<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiConversationModule;
use support\Request;

class AiConversationController extends Controller
{
    public function list(Request $request) { return $this->run([AiConversationModule::class, 'list'], $request); }
    public function add(Request $request) { return $this->run([AiConversationModule::class, 'add'], $request); }
    public function edit(Request $request) { return $this->run([AiConversationModule::class, 'edit'], $request); }
    public function del(Request $request) { return $this->run([AiConversationModule::class, 'del'], $request); }
    public function status(Request $request) { return $this->run([AiConversationModule::class, 'status'], $request); }
    public function detail(Request $request) { return $this->run([AiConversationModule::class, 'detail'], $request); }
}

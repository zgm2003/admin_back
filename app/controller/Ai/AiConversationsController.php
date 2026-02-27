<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiConversationsModule;
use support\Request;

class AiConversationsController extends Controller
{
    public function list(Request $request) { return $this->run([AiConversationsModule::class, 'list'], $request); }
    public function add(Request $request) { return $this->run([AiConversationsModule::class, 'add'], $request); }
    public function edit(Request $request) { return $this->run([AiConversationsModule::class, 'edit'], $request); }
    public function del(Request $request) { return $this->run([AiConversationsModule::class, 'del'], $request); }
    public function status(Request $request) { return $this->run([AiConversationsModule::class, 'status'], $request); }
    public function detail(Request $request) { return $this->run([AiConversationsModule::class, 'detail'], $request); }
}

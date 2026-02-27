<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiMessagesModule;
use support\Request;

class AiMessagesController extends Controller
{
    public function list(Request $request) { return $this->run([AiMessagesModule::class, 'list'], $request); }
    public function del(Request $request) { return $this->run([AiMessagesModule::class, 'del'], $request); }
    public function editContent(Request $request) { return $this->run([AiMessagesModule::class, 'editContent'], $request); }
    public function feedback(Request $request) { return $this->run([AiMessagesModule::class, 'feedback'], $request); }
}

<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiConversationModule;
use support\Request;

class AiConversationController extends Controller
{
    public function list(Request $request)
    {
        $this->run([AiConversationModule::class, 'list'], $request);
        return $this->response();
    }

    public function add(Request $request)
    {
        $this->run([AiConversationModule::class, 'add'], $request);
        return $this->response();
    }

    public function edit(Request $request)
    {
        $this->run([AiConversationModule::class, 'edit'], $request);
        return $this->response();
    }

    public function del(Request $request)
    {
        $this->run([AiConversationModule::class, 'del'], $request);
        return $this->response();
    }

    public function status(Request $request)
    {
        $this->run([AiConversationModule::class, 'status'], $request);
        return $this->response();
    }

    public function detail(Request $request)
    {
        $this->run([AiConversationModule::class, 'detail'], $request);
        return $this->response();
    }
}

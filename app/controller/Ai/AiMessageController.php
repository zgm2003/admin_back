<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiMessageModule;
use support\Request;

class AiMessageController extends Controller
{
    public function list(Request $request)
    {
        $this->run([AiMessageModule::class, 'list'], $request);
        return $this->response();
    }

    public function del(Request $request)
    {
        $this->run([AiMessageModule::class, 'del'], $request);
        return $this->response();
    }
}

<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\UsersLoginLogModule;
use support\Request;

class UsersLoginLogController extends Controller
{
    public function init(Request $request)
    {
        $this->run([UsersLoginLogModule::class, 'init'], $request);
        return $this->response();
    }

    public function list(Request $request)
    {
        $this->run([UsersLoginLogModule::class, 'list'], $request);
        return $this->response();
    }
}

<?php

namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UsersLoginLogModule;
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

<?php

namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UsersLoginLogModule;
use support\Request;

class UsersLoginLogController extends Controller
{
    public function init(Request $request) { return $this->run([UsersLoginLogModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([UsersLoginLogModule::class, 'list'], $request); }
}

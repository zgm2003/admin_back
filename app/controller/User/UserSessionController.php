<?php

namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UserSessionModule;
use support\Request;

class UserSessionController extends Controller
{
    public function list(Request $request) { return $this->run([UserSessionModule::class, 'list'], $request); }
    public function stats(Request $request) { return $this->run([UserSessionModule::class, 'stats'], $request); }

    /** @OperationLog("会话踢下线") @Permission("user.session.kick") */
    public function kick(Request $request) { return $this->run([UserSessionModule::class, 'kick'], $request); }

    /** @OperationLog("会话批量踢下线") @Permission("user.session.kick") */
    public function batchKick(Request $request) { return $this->run([UserSessionModule::class, 'batchKick'], $request); }
}

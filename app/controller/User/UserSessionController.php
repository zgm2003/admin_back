<?php

namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UserSessionModule;
use support\Request;

/**
 * 用户会话管理控制器
 */
class UserSessionController extends Controller
{
    /**
     * 会话列表
     */
    public function list(Request $request)
    {
        $this->run([UserSessionModule::class, 'list'], $request);
        return $this->response();
    }

    /**
     * 会话统计
     */
    public function stats(Request $request)
    {
        $this->run([UserSessionModule::class, 'stats'], $request);
        return $this->response();
    }

    /**
     * 单个踢下线
     * @OperationLog("会话踢下线")
     * @Permission("user.session.kick")
     */
    public function kick(Request $request)
    {
        $this->run([UserSessionModule::class, 'kick'], $request);
        return $this->response();
    }

    /**
     * 批量踢下线
     * @OperationLog("会话批量踢下线")
     * @Permission("user.session.kick")
     */
    public function batchKick(Request $request)
    {
        $this->run([UserSessionModule::class, 'batchKick'], $request);
        return $this->response();
    }
}

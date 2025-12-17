<?php

namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UsersListModule;
use support\Request;

class UsersListController extends Controller
{
    public function init(Request $request)
    {
        $this->run([UsersListModule::class, 'init'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("用户编辑")
     * @Permission("user.edit")
     */
    public function edit(Request $request)
    {
        $this->run([UsersListModule::class, 'edit'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("用户删除")
     * @Permission("user.del")
     */
    public function del(Request $request)
    {
        $this->run([UsersListModule::class, 'del'], $request);
        return $this->response();
    }

    public function list(Request $request)
    {
        $this->run([UsersListModule::class, 'list'], $request);
        return $this->response();
    }

    public function batchEdit(Request $request)
    {
        $this->run([UsersListModule::class, 'batchEdit'], $request);
        return $this->response();
    }

    public function export(Request $request)
    {
        $this->run([UsersListModule::class, 'export'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("用户踢下线")
     * @Permission("user.kick")
     */
    public function kick(Request $request)
    {
        $this->run([UsersListModule::class, 'kick'], $request);
        return $this->response();
    }
}


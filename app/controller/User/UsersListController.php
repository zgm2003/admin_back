<?php

namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UsersListModule;
use support\Request;

class UsersListController extends Controller
{
    public function init(Request $request) { return $this->run([UsersListModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([UsersListModule::class, 'list'], $request); }
    public function batchEdit(Request $request) { return $this->run([UsersListModule::class, 'batchEdit'], $request); }
    public function export(Request $request) { return $this->run([UsersListModule::class, 'export'], $request); }

    /** @OperationLog("用户编辑") @Permission("user_userManager_edit") */
    public function edit(Request $request) { return $this->run([UsersListModule::class, 'edit'], $request); }

    /** @OperationLog("用户删除") @Permission("user_userManager_del") */
    public function del(Request $request) { return $this->run([UsersListModule::class, 'del'], $request); }
}

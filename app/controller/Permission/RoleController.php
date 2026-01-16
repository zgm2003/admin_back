<?php

namespace app\controller\Permission;

use app\controller\Controller;
use app\module\Permission\RoleModule;
use support\Request;

class RoleController extends Controller
{
    public function init(Request $request) { return $this->run([RoleModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([RoleModule::class, 'list'], $request); }

    /** @OperationLog("角色新增") @Permission("role.add") */
    public function add(Request $request) { return $this->run([RoleModule::class, 'add'], $request); }

    /** @OperationLog("角色删除") @Permission("role.del") */
    public function del(Request $request) { return $this->run([RoleModule::class, 'del'], $request); }

    /** @OperationLog("角色修改") @Permission("role.edit") */
    public function edit(Request $request) { return $this->run([RoleModule::class, 'edit'], $request); }

    /** @OperationLog("设置默认角色") @Permission("role.setDefault") */
    public function default(Request $request) { return $this->run([RoleModule::class, 'setDefault'], $request); }
}

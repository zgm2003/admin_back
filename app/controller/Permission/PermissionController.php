<?php

namespace app\controller\Permission;

use app\controller\Controller;
use app\module\Permission\PermissionModule;
use support\Request;

class PermissionController extends Controller
{
    public function init(Request $request) { return $this->run([PermissionModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([PermissionModule::class, 'list'], $request); }

    /** @OperationLog("菜单新增") @Permission("permission.add") */
    public function add(Request $request) { return $this->run([PermissionModule::class, 'add'], $request); }

    /** @OperationLog("菜单编辑") @Permission("permission.edit") */
    public function edit(Request $request) { return $this->run([PermissionModule::class, 'edit'], $request); }

    /** @OperationLog("菜单删除") @Permission("permission.del") */
    public function del(Request $request) { return $this->run([PermissionModule::class, 'del'], $request); }

    /** @OperationLog("菜单批量修改") */
    public function batchEdit(Request $request) { return $this->run([PermissionModule::class, 'batchEdit'], $request); }

    /** @OperationLog("菜单状态修改") */
    public function status(Request $request) { return $this->run([PermissionModule::class, 'status'], $request); }
}

<?php

namespace app\controller\Permission;

use app\controller\Controller;
use app\module\Permission\PermissionModule;
use support\Request;

class PermissionController extends Controller
{
    public function init(Request $request) { return $this->run([PermissionModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([PermissionModule::class, 'list'], $request); }

    /** @OperationLog("菜单新增") @Permission("permission_permission_add") */
    public function add(Request $request) { return $this->run([PermissionModule::class, 'add'], $request); }

    /** @OperationLog("菜单编辑") @Permission("permission_permission_edit") */
    public function edit(Request $request) { return $this->run([PermissionModule::class, 'edit'], $request); }

    /** @OperationLog("菜单删除") @Permission("permission_permission_del") */
    public function del(Request $request) { return $this->run([PermissionModule::class, 'del'], $request); }

    /** @OperationLog("菜单批量修改") */
    public function batchEdit(Request $request) { return $this->run([PermissionModule::class, 'batchEdit'], $request); }

    /** @OperationLog("菜单状态修改") @Permission("permission_permission_status") */
    public function status(Request $request) { return $this->run([PermissionModule::class, 'status'], $request); }

    // APP/H5/小程序 按钮权限
    public function appButtonList(Request $request) { return $this->run([PermissionModule::class, 'appButtonList'], $request); }

    /** @OperationLog("按钮权限新增") @Permission("permission_appButton_add") */
    public function appButtonAdd(Request $request) { return $this->run([PermissionModule::class, 'appButtonAdd'], $request); }

    /** @OperationLog("按钮权限编辑") @Permission("permission_appButton_edit") */
    public function appButtonEdit(Request $request) { return $this->run([PermissionModule::class, 'appButtonEdit'], $request); }

    /** @OperationLog("按钮权限状态修改") @Permission("permission_appButton_status") */
    public function appButtonStatus(Request $request) { return $this->run([PermissionModule::class, 'status'], $request); }

    /** @OperationLog("按钮权限删除") @Permission("permission_appButton_del") */
    public function appButtonDel(Request $request) { return $this->run([PermissionModule::class, 'del'], $request); }
}

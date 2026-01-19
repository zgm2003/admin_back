<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\SystemSettingModule;
use support\Request;

class SystemSettingController extends Controller
{
    public function init(Request $request) { return $this->run([SystemSettingModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([SystemSettingModule::class, 'list'], $request); }

    /** @OperationLog("系统设置新增") @Permission("system_setting_add") */
    public function add(Request $request) { return $this->run([SystemSettingModule::class, 'add'], $request); }

    /** @OperationLog("系统设置编辑") @Permission("system_setting_edit") */
    public function edit(Request $request) { return $this->run([SystemSettingModule::class, 'edit'], $request); }

    /** @OperationLog("系统设置删除") @Permission("system_setting_del") */
    public function del(Request $request) { return $this->run([SystemSettingModule::class, 'del'], $request); }

    /** @OperationLog("系统设置状态变更") @Permission("system_setting_status") */
    public function status(Request $request) { return $this->run([SystemSettingModule::class, 'status'], $request); }
}

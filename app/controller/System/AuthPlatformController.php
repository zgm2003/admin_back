<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\AuthPlatformModule;
use support\Request;

class AuthPlatformController extends Controller
{
    public function init(Request $request) { return $this->run([AuthPlatformModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([AuthPlatformModule::class, 'list'], $request); }

    /** @OperationLog("认证平台新增") @Permission("system_authPlatform_add") */
    public function add(Request $request) { return $this->run([AuthPlatformModule::class, 'add'], $request); }

    /** @OperationLog("认证平台编辑") @Permission("system_authPlatform_edit") */
    public function edit(Request $request) { return $this->run([AuthPlatformModule::class, 'edit'], $request); }

    /** @OperationLog("认证平台删除") @Permission("system_authPlatform_del") */
    public function del(Request $request) { return $this->run([AuthPlatformModule::class, 'del'], $request); }

    /** @OperationLog("认证平台状态变更") @Permission("system_authPlatform_status") */
    public function status(Request $request) { return $this->run([AuthPlatformModule::class, 'status'], $request); }
}

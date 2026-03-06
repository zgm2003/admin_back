<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\TauriVersionModule;
use support\Request;

/**
 * Tauri 版本管理控制器
 */
class TauriVersionController extends Controller
{
    public function init(Request $request) { return $this->run([TauriVersionModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([TauriVersionModule::class, 'list'], $request); }
    public function clientInit(Request $request) { return $this->run([TauriVersionModule::class, 'clientInit'], $request); }

    /** @OperationLog("发布版本") @Permission("devTools_tauriVersion_add") */
    public function add(Request $request) { return $this->run([TauriVersionModule::class, 'add'], $request); }

    /** @OperationLog("编辑版本") @Permission("devTools_tauriVersion_edit") */
    public function edit(Request $request) { return $this->run([TauriVersionModule::class, 'edit'], $request); }

    /** @OperationLog("设为最新版本") @Permission("devTools_tauriVersion_setLatest") */
    public function setLatest(Request $request) { return $this->run([TauriVersionModule::class, 'setLatest'], $request); }

    /** @OperationLog("删除版本") @Permission("devTools_tauriVersion_del") */
    public function del(Request $request) { return $this->run([TauriVersionModule::class, 'del'], $request); }

    /** @OperationLog("切换强制更新") @Permission("devTools_tauriVersion_forceUpdate") */
    public function forceUpdate(Request $request) { return $this->run([TauriVersionModule::class, 'forceUpdate'], $request); }

    public function updateJson(Request $request) { return $this->run([TauriVersionModule::class, 'updateJson'], $request); }
}

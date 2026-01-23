<?php

namespace app\controller\DevTools;

use app\controller\Controller;
use app\module\DevTools\TauriVersionModule;
use support\Request;

/**
 * Tauri 版本管理控制器
 */
class TauriVersionController extends Controller
{
    public function init(Request $request) { return $this->run([TauriVersionModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([TauriVersionModule::class, 'list'], $request); }

    /** @OperationLog("发布版本") @Permission("devTools_tauriVersion_add") */
    public function add(Request $request) { return $this->run([TauriVersionModule::class, 'add'], $request); }

    /** @OperationLog("设为最新版本") @Permission("devTools_tauriVersion_setLatest") */
    public function setLatest(Request $request) { return $this->run([TauriVersionModule::class, 'setLatest'], $request); }

    /** @OperationLog("删除版本") @Permission("devTools_tauriVersion_del") */
    public function del(Request $request) { return $this->run([TauriVersionModule::class, 'del'], $request); }

    public function updateJson(Request $request) { return $this->run([TauriVersionModule::class, 'updateJson'], $request); }
}

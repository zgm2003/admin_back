<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\UploadSettingModule;
use support\Request;

class UploadSettingController extends Controller
{
    public function init(Request $request) { return $this->run([UploadSettingModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([UploadSettingModule::class, 'list'], $request); }

    /** @OperationLog("上传配置新增") @Permission("system_uploadConfig_settingAdd") */
    public function add(Request $request) { return $this->run([UploadSettingModule::class, 'add'], $request); }

    /** @OperationLog("上传配置编辑") @Permission("system_uploadConfig_settingEdit") */
    public function edit(Request $request) { return $this->run([UploadSettingModule::class, 'edit'], $request); }

    /** @OperationLog("上传配置删除") @Permission("system_uploadConfig_settingDel") */
    public function del(Request $request) { return $this->run([UploadSettingModule::class, 'del'], $request); }

    /** @OperationLog("上传配置状态变更") @Permission("system_uploadConfig_settingStatus") */
    public function status(Request $request) { return $this->run([UploadSettingModule::class, 'status'], $request); }
}

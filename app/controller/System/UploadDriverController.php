<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\UploadDriverModule;
use support\Request;

class UploadDriverController extends Controller
{
    public function init(Request $request) { return $this->run([UploadDriverModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([UploadDriverModule::class, 'list'], $request); }

    /** @OperationLog("上传驱动新增") @Permission("uploadDriver.add") */
    public function add(Request $request) { return $this->run([UploadDriverModule::class, 'add'], $request); }

    /** @OperationLog("上传驱动编辑") @Permission("uploadDriver.edit") */
    public function edit(Request $request) { return $this->run([UploadDriverModule::class, 'edit'], $request); }

    /** @OperationLog("上传驱动删除") @Permission("uploadDriver.del") */
    public function del(Request $request) { return $this->run([UploadDriverModule::class, 'del'], $request); }
}

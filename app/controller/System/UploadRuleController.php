<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\UploadRuleModule;
use support\Request;

class UploadRuleController extends Controller
{
    public function init(Request $request) { return $this->run([UploadRuleModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([UploadRuleModule::class, 'list'], $request); }

    /** @OperationLog("上传规则新增") @Permission("uploadRule.add") */
    public function add(Request $request) { return $this->run([UploadRuleModule::class, 'add'], $request); }

    /** @OperationLog("上传规则编辑") @Permission("uploadRule.edit") */
    public function edit(Request $request) { return $this->run([UploadRuleModule::class, 'edit'], $request); }

    /** @OperationLog("上传规则删除") @Permission("uploadRule.del") */
    public function del(Request $request) { return $this->run([UploadRuleModule::class, 'del'], $request); }
}


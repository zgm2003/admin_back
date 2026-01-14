<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\SystemSettingModule;
use support\Request;

class SystemSettingController extends Controller
{
    public function init(Request $request)
    {
        $this->run([SystemSettingModule::class, 'init'], $request);
        return $this->response();
    }

    public function list(Request $request)
    {
        $this->run([SystemSettingModule::class, 'list'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("系统设置新增")
     * @Permission("systemSetting.add")
     */
    public function add(Request $request)
    {
        $this->run([SystemSettingModule::class, 'add'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("系统设置编辑")
     * @Permission("systemSetting.edit")
     */
    public function edit(Request $request)
    {
        $this->run([SystemSettingModule::class, 'edit'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("系统设置删除")
     * @Permission("systemSetting.del")
     */
    public function del(Request $request)
    {
        $this->run([SystemSettingModule::class, 'del'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("系统设置状态变更")
     * @Permission("systemSetting.status")
     */
    public function status(Request $request)
    {
        $this->run([SystemSettingModule::class, 'status'], $request);
        return $this->response();
    }
}

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

    public function add(Request $request) { $this->run([SystemSettingModule::class, 'add'], $request); return $this->response(); }
    public function edit(Request $request) { $this->run([SystemSettingModule::class, 'edit'], $request); return $this->response(); }
    public function del(Request $request) { $this->run([SystemSettingModule::class, 'del'], $request); return $this->response(); }
    public function status(Request $request) { $this->run([SystemSettingModule::class, 'status'], $request); return $this->response(); }

    public function clearCache(Request $request)
    {
        $this->run([SystemSettingModule::class, 'clearCache'], $request);
        return $this->response();
    }
}

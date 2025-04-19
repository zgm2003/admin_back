<?php
namespace app\controller\User;

use app\controller\Controller;
use app\module\User\PermissionModule;
use support\Request;

// Webman 原生 Request 类
// 正确引用业务模块
class PermissionController extends Controller{

    public function init(Request $request){

        $this->run([PermissionModule::class,'init'],$request);
        return $this->response();

    }

    public function add(Request $request)
    {
        $this->run([PermissionModule::class,'add'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([PermissionModule::class,'edit'],$request);
        return $this->response();
    }
    public function del(Request $request)
    {
        $this->run([PermissionModule::class,'del'],$request);
        return $this->response();
    }
    public function list(Request $request)
    {
        $this->run([PermissionModule::class,'list'],$request);
        return $this->response();
    }
    public function batchEdit(Request $request)
    {
        $this->run([PermissionModule::class,'batchEdit'],$request);
        return $this->response();
    }
    public function status(Request $request)
    {
        $this->run([PermissionModule::class,'status'],$request);
        return $this->response();
    }


}

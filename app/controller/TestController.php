<?php
namespace app\controller;

namespace app\controller;
use support\Request;             // Webman 原生 Request 类
use app\module\TestModule;      // 正确引用业务模块

class TestController extends Controller{

    public function init(Request $request){

        $this->run([TestModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([TestModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([TestModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([TestModule::class,'edit'],$request);
        return $this->response();
    }
    public function batchEdit(Request $request){
        $this->run([TestModule::class,'batchEdit'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([TestModule::class,'list'],$request);
        return $this->response();
    }
    public function test(Request $request){
        $this->run([TestModule::class,'test'],$request);
        return $this->response();
    }


}

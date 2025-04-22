<?php
namespace app\controller\Ai;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\Ai\AiModule;      // 正确引用业务模块

class AiController extends Controller{

    public function init(Request $request){

        $this->run([AiModule::class,'init'],$request);
        return $this->response();
    }
    public function init1(Request $request){

        $this->run([AiModule::class,'init1'],$request);
        return $this->response();
    }
    public function add(Request $request){

        $this->run([AiModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([AiModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([AiModule::class,'edit'],$request);
        return $this->response();
    }
    public function batchEdit(Request $request){
        $this->run([AiModule::class,'batchEdit'],$request);
        return $this->response();
    }
    public function list(Request $request){
        $this->run([AiModule::class,'list'],$request);
        return $this->response();
    }
    public function list1(Request $request){
        $this->run([AiModule::class,'list1'],$request);
        return $this->response();
    }
    public function homeModule(Request $request){
        $this->run([AiModule::class,'homeModule'],$request);
        return $this->response();
    }
    public function categoryList(Request $request){
        $this->run([AiModule::class,'categoryList'],$request);
        return $this->response();
    }
}

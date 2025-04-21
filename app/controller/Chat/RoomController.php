<?php
namespace app\controller\Chat;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\Chat\RoomModule;      // 正确引用业务模块

class RoomController extends Controller{

    public function init(Request $request){
        $this->run([RoomModule::class,'init'],$request);
        return $this->response();
    }
    public function add(Request $request){
        $this->run([RoomModule::class,'add'],$request);
        return $this->response();
    }
    public function del(Request $request){
        $this->run([RoomModule::class,'del'],$request);
        return $this->response();
    }
    public function edit(Request $request){
        $this->run([RoomModule::class,'edit'],$request);
        return $this->response();
    }
    public function list(Request $request){
        $this->run([RoomModule::class,'list'],$request);
        return $this->response();
    }
    public function isEnable(Request $request){
        $this->run([RoomModule::class,'isEnable'],$request);
        return $this->response();
    }
}

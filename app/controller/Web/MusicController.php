<?php
namespace app\controller\Web;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\Web\MusicModule;      // 正确引用业务模块

class MusicController extends Controller{

    public function init(Request $request){
        $this->run([MusicModule::class,'init'],$request);
        return $this->response();
    }
    public function add(Request $request){
        $this->run([MusicModule::class,'add'],$request);
        return $this->response();
    }
    public function del(Request $request){
        $this->run([MusicModule::class,'del'],$request);
        return $this->response();
    }
    public function edit(Request $request){
        $this->run([MusicModule::class,'edit'],$request);
        return $this->response();
    }
    public function list(Request $request){
        $this->run([MusicModule::class,'list'],$request);
        return $this->response();
    }

}

<?php
namespace app\controller\Web;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\Web\AlbumModule;      // 正确引用业务模块

class AlbumController extends Controller{

    public function init(Request $request){

        $this->run([AlbumModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([AlbumModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([AlbumModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([AlbumModule::class,'edit'],$request);
        return $this->response();
    }
    public function batchEdit(Request $request){
        $this->run([AlbumModule::class,'batchEdit'],$request);
        return $this->response();
    }
    public function list(Request $request){
        $this->run([AlbumModule::class,'list'],$request);
        return $this->response();
    }
    public function detail(Request $request){
        $this->run([AlbumModule::class,'detail'],$request);
        return $this->response();
    }
    public function check(Request $request){
        $this->run([AlbumModule::class,'check'],$request);
        return $this->response();
    }
}

<?php
namespace app\controller\Article;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\Article\TagModule;      // 正确引用业务模块


class TagController extends Controller{

    public function init(Request $request){

        $this->run([TagModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([TagModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([TagModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([TagModule::class,'edit'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([TagModule::class,'list'],$request);
        return $this->response();
    }



}

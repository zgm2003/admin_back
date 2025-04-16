<?php
namespace app\controller\Ai;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\Ai\CategoryModule;      // 正确引用业务模块


class CategoryController extends Controller{

    public function init(Request $request){

        $this->run([CategoryModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([CategoryModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([CategoryModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([CategoryModule::class,'edit'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([CategoryModule::class,'list'],$request);
        return $this->response();
    }

}

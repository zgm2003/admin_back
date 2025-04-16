<?php
namespace app\controller\Web;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\Web\VisitorModule;      // 正确引用业务模块

class VisitorController extends Controller{

    public function del(Request $request){
        $this->run([VisitorModule::class,'del'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([VisitorModule::class,'list'],$request);
        return $this->response();
    }

}

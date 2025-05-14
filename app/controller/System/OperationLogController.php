<?php
namespace app\controller\System;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\System\OperationLogModule;      // 正确引用业务模块

class OperationLogController extends Controller{

    public function init(Request $request){

        $this->run([OperationLogModule::class,'init'],$request);
        return $this->response();
    }


    public function del(Request $request){
        $this->run([OperationLogModule::class,'del'],$request);
        return $this->response();
    }


    public function list(Request $request){
        $this->run([OperationLogModule::class,'list'],$request);
        return $this->response();
    }



}

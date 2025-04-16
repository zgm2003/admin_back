<?php
namespace app\controller\Web;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\Web\CommentModule;      // 正确引用业务模块

class CommentController extends Controller{

    public function init(Request $request){

        $this->run([CommentModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([CommentModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([CommentModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([CommentModule::class,'edit'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([CommentModule::class,'list'],$request);
        return $this->response();
    }
    public function listList(Request $request){
        $this->run([CommentModule::class,'listList'],$request);
        return $this->response();
    }
    public function batchEdit(Request $request){
        $this->run([CommentModule::class,'batchEdit'],$request);
        return $this->response();
    }

}

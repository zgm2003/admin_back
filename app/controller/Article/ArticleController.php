<?php
namespace app\controller\Article;

use app\controller\Controller;
use support\Request;             // Webman 原生 Request 类
use app\module\Article\ArticleModule;      // 正确引用业务模块

class ArticleController extends Controller{

    public function init(Request $request){

        $this->run([ArticleModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([ArticleModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([ArticleModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([ArticleModule::class,'edit'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([ArticleModule::class,'list'],$request);
        return $this->response();
    }
    public function batchEdit(Request $request){
        $this->run([ArticleModule::class,'batchEdit'],$request);
        return $this->response();
    }
    public function testPrompt(Request $request){
        $this->run([ArticleModule::class,'testPrompt'],$request);
        return $this->response();
    }
    public function confirmPrompt(Request $request){
        $this->run([ArticleModule::class,'confirmPrompt'],$request);
        return $this->response();
    }
    public function toModel(Request $request){
        $this->run([ArticleModule::class,'toModel'],$request);
        return $this->response();
    }
    public function toReview(Request $request){
        $this->run([ArticleModule::class,'toReview'],$request);
        return $this->response();
    }
    public function toRelease(Request $request){
        $this->run([ArticleModule::class,'toRelease'],$request);
        return $this->response();
    }
    public function toRemove(Request $request){
        $this->run([ArticleModule::class,'toRemove'],$request);
        return $this->response();
    }
}

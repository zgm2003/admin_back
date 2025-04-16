<?php
namespace app\controller\AiWorkLine\E_commerce;

use app\controller\Controller;
use support\Request;
use app\module\AiWorkLine\E_commerce\GoodsModule;

class GoodsController extends Controller{

    public function init(Request $request){

        $this->run([GoodsModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([GoodsModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([GoodsModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([GoodsModule::class,'edit'],$request);
        return $this->response();
    }
    public function batchEdit(Request $request){
        $this->run([GoodsModule::class,'batchEdit'],$request);
        return $this->response();
    }
    public function list(Request $request){
        $this->run([GoodsModule::class,'list'],$request);
        return $this->response();
    }
    public function testPrompt(Request $request){
        $this->run([GoodsModule::class,'testPrompt'],$request);
        return $this->response();
    }
    public function confirmPrompt(Request $request){
        $this->run([GoodsModule::class,'confirmPrompt'],$request);
        return $this->response();
    }
    public function toOcr(Request $request){
        $this->run([GoodsModule::class,'toOcr'],$request);
        return $this->response();
    }
    public function toModel(Request $request){
        $this->run([GoodsModule::class,'toModel'],$request);
        return $this->response();
    }
    public function toSpeech(Request $request){
        $this->run([GoodsModule::class,'toSpeech'],$request);
        return $this->response();
    }
    public function getImage(Request $request){
        $this->run([GoodsModule::class,'getImage'],$request);
        return $this->response();
    }
}

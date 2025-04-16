<?php
namespace app\controller\AiWorkLine\AiImageVideo;

use app\controller\Controller;
use app\module\AiWorkLine\AiImageVideo\AiImageVideoModule;
use support\Request;

class AiImageVideoController extends Controller{

    public function init(Request $request){

        $this->run([AiImageVideoModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([AiImageVideoModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([AiImageVideoModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([AiImageVideoModule::class,'edit'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([AiImageVideoModule::class,'list'],$request);
        return $this->response();
    }
    public function batchEdit(Request $request){
        $this->run([AiImageVideoModule::class,'batchEdit'],$request);
        return $this->response();
    }
    public function toImage(Request $request){
        $this->run([AiImageVideoModule::class,'toImage'],$request);
        return $this->response();
    }
    public function toVideo(Request $request){
        $this->run([AiImageVideoModule::class,'toVideo'],$request);
        return $this->response();
    }

}

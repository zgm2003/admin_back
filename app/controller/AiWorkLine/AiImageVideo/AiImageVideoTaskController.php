<?php
namespace app\controller\AiWorkLine\AiImageVideo;

use app\controller\Controller;
use app\module\AiWorkLine\AiImageVideo\AiImageVideoTaskModule;
use support\Request;

class AiImageVideoTaskController extends Controller{

    public function init(Request $request){

        $this->run([AiImageVideoTaskModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([AiImageVideoTaskModule::class,'add'],$request);
        return $this->response();
    }
    public function del(Request $request){
        $this->run([AiImageVideoTaskModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([AiImageVideoTaskModule::class,'edit'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([AiImageVideoTaskModule::class,'list'],$request);
        return $this->response();
    }
    public function testPrompt(Request $request){
        $this->run([AiImageVideoTaskModule::class,'testPrompt'],$request);
        return $this->response();
    }
    public function toPrompt(Request $request){
        $this->run([AiImageVideoTaskModule::class,'toPrompt'],$request);
        return $this->response();
    }

}

<?php
namespace app\controller\AiWorkLine\AiImageVideo;

use app\controller\Controller;
use app\module\AiWorkLine\AiImageVideo\AiImageVideoPromptModule;
use support\Request;

class AiImageVideoPromptController extends Controller{

    public function init(Request $request){

        $this->run([AiImageVideoPromptModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([AiImageVideoPromptModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([AiImageVideoPromptModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([AiImageVideoPromptModule::class,'edit'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([AiImageVideoPromptModule::class,'list'],$request);
        return $this->response();
    }
    public function testPrompt(Request $request){
        $this->run([AiImageVideoPromptModule::class,'testPrompt'],$request);
        return $this->response();
    }

    public function changePrmopt(Request $request){
        $this->run([AiImageVideoPromptModule::class,'changePrmopt'],$request);
        return $this->response();
    }
}

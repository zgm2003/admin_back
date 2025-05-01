<?php
namespace app\controller\Chat;

use app\controller\Controller;
use app\module\Chat\ChatModule;
use support\Request;
class ChatController extends Controller{

    public function init(Request $request){

        $this->run([ChatModule::class,'init'],$request);
        return $this->response();
    }

    public function send(Request $request){

        $this->run([ChatModule::class,'send'],$request);
        return $this->response();
    }
    public function online(Request $request){

        $this->run([ChatModule::class,'online'],$request);
        return $this->response();
    }

    public function list(Request $request){

        $this->run([ChatModule::class,'list'],$request);
        return $this->response();
    }
    public function exit(Request $request){

        $this->run([ChatModule::class,'exit'],$request);
        return $this->response();
    }
}

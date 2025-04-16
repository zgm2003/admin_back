<?php
namespace app\controller\AiWorkLine\E_commerce;

use app\controller\Controller;
use app\module\AiWorkLine\E_commerce\AccountModule;
use support\Request;

class AccountController extends Controller{

    public function init(Request $request){
        $this->run([AccountModule::class,'init'],$request);
        return $this->response();
    }
    public function del(Request $request){
        $this->run([AccountModule::class,'del'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([AccountModule::class,'list'],$request);
        return $this->response();
    }

}

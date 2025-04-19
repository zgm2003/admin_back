<?php
namespace app\controller\AiWorkLine\E_commerce\platform;

use app\controller\Controller;
use app\module\AiWorkLine\E_commerce\platform\PinduoduoModule;
use support\Request;

class PinduoduoController extends Controller{

    public function callback(Request $request)
    {
        $this->run([PinduoduoModule::class,'callback'],$request);
        return $this->response();

    }
    public function loginKey(Request  $request){

        $this->run([PinduoduoModule::class,'loginKey'],$request);
        return $this->response();

    }



}

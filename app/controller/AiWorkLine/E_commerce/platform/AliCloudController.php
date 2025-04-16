<?php
namespace app\controller\AiWorkLine\E_commerce\platform;

use app\controller\Controller;
use app\module\AiWorkLine\E_commerce\platform\AliCloudModule;
use support\Request;

class AliCloudController extends Controller{
    public function getToken(Request  $request){

        $this->run([AliCloudModule::class,'getToken'],$request);
        return $this->response();

    }

}

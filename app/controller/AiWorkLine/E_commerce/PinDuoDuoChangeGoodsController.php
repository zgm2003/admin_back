<?php
namespace app\controller\AiWorkLine\E_commerce;

use app\controller\Controller;
use app\Module\AiWorkLine\E_commerce\PinDuoDuoChangeGoodsModule;
use support\Request;
class PinDuoDuoChangeGoodsController extends Controller{

    public function init(Request $request){

        $this->run([PinDuoDuoChangeGoodsModule::class,'init'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([PinDuoDuoChangeGoodsModule::class,'list'],$request);
        return $this->response();
    }

    public function export(Request $request){
        $this->run([PinDuoDuoChangeGoodsModule::class,'export'],$request);
        return $this->response();
    }
}

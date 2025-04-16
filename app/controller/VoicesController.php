<?php
namespace app\controller;

use app\enum\VoicesEnum;
use app\lib\AliCloud\TTS;
use app\module\VoicesModule;
use app\dep\VoicesDep;
use support\Request;

class VoicesController extends Controller{

    public function init(Request $request){

        $this->run([VoicesModule::class,'init'],$request);
        return $this->response();
    }


    public function del(Request $request){
        $this->run([VoicesModule::class,'del'],$request);
        return $this->response();
    }
    public function listen(Request $request)
    {
        $param = $request->all();
        $voicesDep = new VoicesDep();
        $resDep = $voicesDep->first($param['id']);
        $text = '这是一段测试音频';
        $sampleRate = VoicesEnum::$hzArr[$resDep->sampling_rates];
        $result = TTS::TssSync($text,$sampleRate,$resDep->code);
        return $result;
    }



    public function list(Request $request){
        $this->run([VoicesModule::class,'list'],$request);
        return $this->response();
    }
}

<?php

namespace app\module;

use app\dep\VoicesDep;
use app\enum\CommonEnum;
use app\enum\VoicesEnum;
use app\service\DictService;


class VoicesModule extends BaseModule
{
    public $voicesDep;

    public function __construct()
    {
        $this->voicesDep = new VoicesDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setVoicesHzArr()
            ->setVoicesQualityArr()
            ->getDict();

        return self::response($data);
    }


    public function del($request)
    {

        $param = $request->all();

        $dep = $this->voicesDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }


    public function list($request)
    {

        $dep = $this->voicesDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 20;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item){
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'code' => $item['code'],
                'voice_style' => $item['voice_style'],
                'digital_type' => $item['digital_type'],
                'supported_scene' => $item['supported_scene'],
                'sampling_rates' => $item['sampling_rates'],
                'spampling_rates_show' => VoicesEnum::$hzArr[$item['sampling_rates']],
                'support_field1' => $item['support_field1'],
                'support_field2' => $item['support_field2'],
                'quality' => $item['quality'],
                'quality_show' => VoicesEnum::$qualityArr[$item['quality']],
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString()
            ];
        });
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];

        return self::response($data);
    }


}


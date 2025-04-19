<?php

namespace app\module\AiWorkLine\AiImageVideo;

use app\dep\AiWorkLine\AiImageVideo\AiImageVideoPromptDep;
use app\enum\AiImageVideoEnum;
use app\lib\AliCloud\AigcSdk;
use app\module\BaseModule;
use app\service\DictService;


class AiImageVideoPromptModule extends BaseModule
{
    public $promptDep;

    public function __construct()
    {
        $this->promptDep = new AiImageVideoPromptDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setAiImageVideoPlatformArr()
            ->setAiImageVideoPromptArr()
            ->getDict();

        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();

        if (empty($param['title']) || empty($param['prompt'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $data = [
            'title' => $param['title'],
            'prompt' => $param['prompt'],
            'image' => $param['image'] ?? ""
        ];

        $this->promptDep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->promptDep;

        $dep->del($param['id']);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();
        $dep = $this->promptDep;

        if (empty($param['title']) || empty($param['prompt'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $resDep = $dep->firstByTitle($param['title']);
        if ($resDep && $resDep['id'] != $param['id']) {
            return self::response([], '标题已存在', 100);
        }
        $data = [
            'title' => $param['title'],
            'prompt' => $param['prompt'],
            'image' => $param['image'] ?? ""
        ];
        $dep->edit($param['id'], $data);

        return self::response();
    }

    public function list($request)
    {

        $dep = $this->promptDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 50;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'prompt' => $item['prompt'],
                'image' => $item['image']??"",
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

    public function testPrompt($request)
    {
        $param = $request->all();
        if (
            empty($param['name']) || empty($param['platform']) || empty($param['prompt'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }

        $prompt = $param['prompt'];
        $chat = str_replace('{name}', $param['name'], $prompt);
        $chat = str_replace('{platform}', AiImageVideoEnum::$platformArr[$param['platform']], $chat);
        $sdk = new AigcSdk();
        $resChat = $sdk->chat("你现在是一名专业的运营", $chat);
        $origin = $resChat['output']['choices'][0]['message']['content'];

        return self::response(['origin_result' => $origin]);
    }
    public function changePrmopt($request)
    {
        $param = $request->all();
        $dep = $this->promptDep;
        if (
            empty($param['id'])
        ){
            return self::response([], '缺少ID', 100);
        }
        $resDep = $dep->first($param['id']);
        $data = [
            'prompt' => $resDep['prompt'],
            'image' => $resDep['image']??""
        ];
        return self::response($data);
    }

}


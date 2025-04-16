<?php

namespace app\module\AiWorkLine\AiImageVideo;

use app\dep\AiWorkLine\AiImageVideo\AiImageVideoPromptDep;
use app\dep\AiWorkLine\AiImageVideo\AiImageVideoTaskDep;
use app\dep\SystemDep;
use app\enum\AiImageVideoEnum;
use app\enum\CommonEnum;
use app\lib\AliCloud\AigcSdk;
use app\module\BaseModule;
use app\service\DictService;
use Webman\RedisQueue\Redis;

class AiImageVideoTaskModule extends BaseModule
{
    public $aiImageVideoTaskDep;
    public $AiImageVideoEnum;
    public $systemDep;
    public $promptDep;

    public function __construct()
    {
        $this->aiImageVideoTaskDep = new AiImageVideoTaskDep();
        $this->AiImageVideoEnum = new AiImageVideoEnum();
        $this->systemDep = new SystemDep();
        $this->promptDep = new AiImageVideoPromptDep();

    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setAiImageVideoTaskStatusArr()
            ->setAiImageVideoPlatformArr()
            ->setAiImageVideoPromptArr()
            ->getDict();
        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();
        $dep = $this->aiImageVideoTaskDep;
        if (
            empty($param['name']) || empty($param['platform']) || empty($param['prompt'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $data = [
            'name' => $param['name'],
            'platform' => $param['platform'],
            'prompt' => $param['prompt'],
            'status' => $this->AiImageVideoEnum::TASK_DRAFT,
        ];
        $dep->add($data);
        return self::response();

    }



    public function del($request)
    {

        $param = $request->all();

        $dep = $this->aiImageVideoTaskDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();

        $dep = $this->aiImageVideoTaskDep;
        $data = [
            'name' => $param['name'],
            'platform' => $param['platform'],
            'prompt' => $param['prompt'],
        ];
        $dep->edit($param['id'], $data);

        return self::response();
    }

    public function list($request)
    {

        $dep = $this->aiImageVideoTaskDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 20;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);


        $resCensus = $this->aiImageVideoTaskDep->census()->keyBy('status');
        $data['census'] = $resCensus->toArray();

        $data['list'] = $resList->map(function ($item){
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'platform' => $item['platform'],
                'platform_name' => AiImageVideoEnum::$platformArr[$item['platform']],
                'prompt' => $item['prompt'],
                'status' => $item['status'],
                'status_msg' => $item['status_msg'],
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString(),
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

    public function toPrompt($request)
    {
        $param = $request->all();

        $dep = $this->aiImageVideoTaskDep;
        $data = [
            'status' => $this->AiImageVideoEnum::TASK_PROMPT
        ];

        $dep->edit($param['id'], $data);

        if (!is_array($param['id'])) {
            $param['id'] = [$param['id']];
        }
        // 队列名
        $queue = 'ai-task-prompt';
        foreach ($param['id'] as $id) {
            Redis::send($queue, $id);
        }
        return self::response();
    }


}


<?php

namespace app\module\AiWorkLine\AiImageVideo;

use app\dep\AiWorkLine\AiImageVideo\AiImageVideoDep;
use app\dep\AiWorkLine\AiImageVideo\AiImageVideoTaskDep;
use app\dep\SystemDep;
use app\enum\AiImageVideoEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use Webman\RedisQueue\Redis;

class AiImageVideoModule extends BaseModule
{
    public $aiImageVideoDep;
    public $AiImageVideoEnum;
    public $systemDep;
    public $taskDep;

    public function __construct()
    {
        $this->aiImageVideoDep = new AiImageVideoDep();
        $this->AiImageVideoEnum = new AiImageVideoEnum();
        $this->systemDep = new SystemDep();
        $this->taskDep = new AiImageVideoTaskDep();

    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setAiImageVideoStatusArr()
            ->setAiImageVideoPlatformArr()
            ->setAiImageVideoImageSizeArr()
            ->setAiImageVideoTaskNameArr()
            ->setAiImageVideoPromptArr()
            ->getDict();
        return self::response($data);
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->aiImageVideoDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {
        $param = $request->all();
        $dep = $this->aiImageVideoDep;
        foreach (['title','text','image_prompt','video_prompt','imageSize','batchSize','numInferenceSteps','guidanceScale'] as $f) {
            if (empty($param[$f])) {
                return self::response([], "{$f} 不能为空", 100);
            }
        }
        $data = [
            'title' => $param['title'],
            'text' => $param['text'],
            'image_prompt' => $param['image_prompt'],
            'video_prompt' => $param['video_prompt'],
            'imageSize' => $param['imageSize'],
            'batchSize' => $param['batchSize'],
            'numInferenceSteps' => $param['numInferenceSteps'],
            'guidanceScale' => $param['guidanceScale'],
            'referenceImage' => $param['referenceImage']??"",
            'image_list_success' => json_encode($param['image_list_success']),
        ];
        $dep->edit($param['id'], $data);

        return self::response();
    }

    public function list($request)
    {

        $dep = $this->aiImageVideoDep;
        $taskDep = $this->taskDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 20;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $resCensus = $this->aiImageVideoDep->census()->keyBy('status');
        $data['census'] = $resCensus->toArray();

        $data['list'] = $resList->map(function ($item) use ($taskDep){
            $resTask = $taskDep->first($item['task_id']);
            return [
                'id' => $item['id'],
                'task_id' => $item['task_id'],

                'name' => $resTask['name'],
                'platform' => $resTask['platform'],
                'platform_name' => AiImageVideoEnum::$platformArr[$resTask['platform']],

                'title' => $item['title'],
                'text' => $item['text'],
                'image_prompt' => $item['image_prompt'],
                'video_prompt' => $item['video_prompt'],

                'imageSize' => $item['imageSize'],
                'imageSize_show' => AiImageVideoEnum::$imageSizeArr[$item['imageSize']],
                'batchSize' => $item['batchSize'],
                'numInferenceSteps' => $item['numInferenceSteps'],
                'guidanceScale' => $item['guidanceScale'],
                'referenceImage' => $item['referenceImage'],

                'image_list' => json_decode($item->image_list ?? '[]'),
                'image_list_success' => json_decode($item->image_list_success ?? '[]'),
                'video' => $item['video'],

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
    public function batchEdit($request)
    {

        $param = $request->all();
        $dep = $this->aiImageVideoDep;
        $id = $param['ids'];

        if ($param['field'] == 'imageSize') {
            if (empty($param['imageSize'])) {
                return self::response([], '图片尺寸不能为空', 100);
            }
            $data = [
                'imageSize' => $param['imageSize'],
            ];
            $dep->edit($id, $data);
        }

        if ($param['field'] == 'batchSize') {
            if (empty($param['batchSize'])) {
                return self::response([], '生成数量不能为空', 100);
            }
            $data = [
                'batchSize' => $param['batchSize'],
            ];
            $dep->edit($id, $data);
        }

        if ($param['field'] == 'numInferenceSteps') {
            if (empty($param['numInferenceSteps'])) {
                return self::response([], '思考步骤不能为空', 100);
            }
            $data = [
                'numInferenceSteps' => $param['numInferenceSteps'],
            ];
            $dep->edit($id, $data);
        }

        if ($param['field'] == 'guidanceScale') {
            if (empty($param['guidanceScale'])) {
                return self::response([], '指导程度不能为空', 100);
            }
            $data = [
                'guidanceScale' => $param['guidanceScale'],
            ];
            $dep->edit($id, $data);
        }

        if ($param['field'] == 'referenceImage') {
            if (empty($param['referenceImage'])) {
                return self::response([], '参考图不能为空', 100);
            }
            $data = [
                'referenceImage' => $param['referenceImage'],
            ];
            $dep->edit($id, $data);
        }

        return self::response();
    }

    public function toImage($request)
    {
        $param = $request->all();
        $dep = $this->aiImageVideoDep;

        $data = [
            'status' => $this->AiImageVideoEnum::IMAGE,
        ];

        $dep->edit($param['id'], $data);

        if (!is_array($param['id'])) {
            $param['id'] = [$param['id']];
        }
        // 队列名
        $queue = 'ai-image';
        foreach ($param['id'] as $id) {
            Redis::send($queue, $id);
        }
        return self::response();
    }

    public function toVideo($request)
    {
        $param = $request->all();
        $dep = $this->aiImageVideoDep;
        $resDep = $dep->first($param['id']);
        //image_list_success不存在，或者是[]，或者长度超过1，就return出去
        if(!$resDep['image_list_success'] || json_decode($resDep['image_list_success']) == [] || count(json_decode($resDep['image_list_success'])) > 1){
            return self::response([], '请先选择图片或图片选择过多（最多选一张）', 100);
        }
        $data = [
            'status' => $this->AiImageVideoEnum::VIDEO,
        ];

        $dep->edit($param['id'], $data);

        if (!is_array($param['id'])) {
            $param['id'] = [$param['id']];
        }
        // 队列名
        $queue = 'ai-video';
        foreach ($param['id'] as $id) {
            Redis::send($queue, $id);
        }
        return self::response();
    }


}


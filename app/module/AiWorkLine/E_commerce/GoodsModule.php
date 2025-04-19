<?php

namespace app\module\AiWorkLine\E_commerce;

use app\dep\AiWorkLine\E_commerce\GoodsDep;
use app\dep\SystemDep;
use app\dep\User\UsersDep;
use app\dep\VoicesDep;
use app\enum\CommonEnum;
use app\enum\GoodsEnum;
use app\lib\AliCloud\AigcSdk;
use app\module\BaseModule;
use app\service\DictService;
use Webman\RedisQueue\Redis;

class GoodsModule extends BaseModule
{
    public $goodsDep;
    public $usersDep;
    public $GoodsEnum;
    public $systemDep;
    public $voicesDep;

    public function __construct()
    {
        $this->goodsDep = new GoodsDep();
        $this->usersDep = new UsersDep();
        $this->GoodsEnum = new GoodsEnum();
        $this->systemDep = new SystemDep();
        $this->voicesDep = new VoicesDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setGoodsStatusArr()
            ->setGoodsPlatformArr()
            ->setVoicesArr()
            ->getDict();
        $data['goods_prompt'] = $this->systemDep->first()->goods_prompt;
        return self::response($data);
    }

    public function add($request)
    {
        $resSystem = $this->systemDep->first();
        $param = $request->all();

        if ($param['platform'] == GoodsEnum::PLATFORM_PINDUODUO) {
            // 拼多多精选库的添加逻辑
            $errors = [];
            $successCount = 0;
            foreach ($param['data'] as $item) {
                $resFind = $this->goodsDep->firstByGoodsIdAndPlatform($item['goods_id'], $param['platform']);
                if ($resFind) {
                    $errors[] = "商品ID {$item['goods_id']} 已经添加，请勿重复添加";
                    continue;
                }
                $data = [
                    'goods_id' => $item['goods_id'] ?? "",
//                    'goods_sign'  => $item['goods_sign'] ?? "",
//                    'sales'       => $item['sales'] ?? '',
                    'title' => $item['title'] ?? "",
                    'main_img' => $item['main_img'] ?? "",
                    'price' => $item['price'] ?? 0,
                    'commission' => $item['commission'] ?? 0,
                    'shop_title' => $item['shop_title'] ?? "",
                    'platform' => $param['platform'],
                    'link' => "https://mobile.yangkeduo.com/goods2.html?goods_id=" . $item['goods_id'],
                    'tips' => $resSystem->pinduoduo_goods_prompt,
                ];
                if (!empty($data['goods_id'])) {
                    $this->goodsDep->add($data);
                    $successCount++; // 成功添加的计数
                }
            }
            // 返回结果
            $message = $successCount > 0 ? "{$successCount} 个商品添加成功" : "没有商品被添加";
            if (!empty($errors)) {
                return self::response(['errors' => $errors], $message, 200);
            }
            return self::response([], $message);
        } else {
            // 普通商品添加逻辑
            $resDep = $this->goodsDep->firstByGoodsIdAndPlatform($param['productId'], $param['platform']);
            if ($resDep) {
                return self::response([], '商品已存在', 100);
            }
            $data = [
                'goods_id' => $param['productId'] ?? "",
                'title' => $param['productName'] ?? "",
                'main_img' => $param['productImage'] ?? "",
                'link' => $param['productLink'] ?? "",
                'price' => $param['productPrice'] ?? 0,
                'commission' => $param['productCommission'] ?? 0,
                'shop_title' => $param['shopName'] ?? "",
                'platform' => $param['platform'],
                'tips' => $resSystem->goods_prompt,
            ];
            $this->goodsDep->add($data);
            return self::response([], '添加成功');
        }
    }


    public function del($request)
    {

        $param = $request->all();

        $dep = $this->goodsDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();

        $dep = $this->goodsDep;
        $data = [
            'ocr' => $param['ocr'],
            'tips' => $param['tips'],
            'point' => $param['point'],
            'image_list_success' => json_encode($param['image_list_success']),
        ];
        $dep->edit($param['id'], $data);

        return self::response();
    }
    public function batchEdit($request)
    {

        $param = $request->all();
        $dep = $this->goodsDep;
        $id = $param['ids'];
        if ($param['field'] == 'tips') {
            if (empty($param['tips'])) {
                return self::response([], '提示词不能为空', 100);
            }
            $data = [
                'tips' => $param['tips'],
            ];
            $dep->edit($id, $data);
        }
        return self::response();
    }

    public function list($request)
    {

        $dep = $this->goodsDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 50;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $resCensus = $this->goodsDep->census()->keyBy('status');
        $data['census'] = $resCensus->toArray();

        $data['list'] = $resList->map(function ($item){
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'goods_id' => $item['goods_id'],
                'platform' => $item['platform'],
                'image_list' => json_decode($item->image_list ?? '[]'),
                'image_list_success' => json_decode($item->image_list_success ?? '[]'),
                'platform_name' => GoodsEnum::$platformArr[$item['platform']],
                'link' => $item['link'],
                'price' => $item['price'],
                'commission' => $item['commission'],
                'main_img' => $item['main_img'],
                'shop_title' => $item['shop_title'],
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString(),

                'tips' => $item['tips'],
                'status' => $item['status'],
                'status_msg' => $item['status_msg'],
                'ocr' => $item['ocr'],
                'point' => $item->point,
                'model_origin' => $item->model_origin,
                'music_url' => $item->music_url,
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
            empty($param['name']) || empty($param['ocr']) || empty($param['prompt'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }

        $prompt = $param['prompt'];
        $chat = str_replace('{name}', $param['name'], $prompt);
        $chat = str_replace('{ocr}', $param['ocr'], $chat);
        $sdk = new AigcSdk();
        $resChat = $sdk->chat("我是一名产品推荐官", $chat);
        $origin = $resChat['output']['choices'][0]['message']['content'];

        return self::response(['origin_result' => $origin]);
    }

    public function confirmPrompt($request)
    {
        $param = $request->all();

        if (empty($param['prompt'])) {
            return self::response([], '请输入提示词', 100);
        }

        $dep = $this->systemDep;
        $dep->edit(1, ['goods_prompt' => $param['prompt']]);
        return self::response();
    }


    public function toOcr($request)
    {
        $param = $request->all();

        $dep = $this->goodsDep;
        $data = [
            'status' => $this->GoodsEnum::OCR
        ];

        $dep->edit($param['id'], $data);

        if (!is_array($param['id'])) {
            $param['id'] = [$param['id']];
        }
        // 队列名
        $queue = 'goods-ocr';
        foreach ($param['id'] as $id) {
            Redis::send($queue, $id);
        }
        return self::response();
    }

    public function toModel($request)
    {
        $param = $request->all();

        $dep = $this->goodsDep;
        $data = [
            'status' => $this->GoodsEnum::POINT
        ];

        $dep->edit($param['id'], $data);

        if (!is_array($param['id'])) {
            $param['id'] = [$param['id']];
        }
        // 队列名
        $queue = 'goods-model';
        foreach ($param['id'] as $id) {
            Redis::send($queue, $id);
        }
        return self::response();
    }

    public function toSpeech($request)
    {
        $param = $request->all();
        if (
            empty($param['voices_id'])
        ){
            return self::response([], "请选择音色", 100);
        }

        $dep = $this->goodsDep;
        $data = [
            'status' => $this->GoodsEnum::SPEECH
        ];

        $dep->edit($param['id'], $data);

        if (!is_array($param['id'])) {
            $param['id'] = [$param['id']];
        }
        // 队列名
        $queue = 'goods-speech';
        foreach ($param['id'] as $id) {
            $data = [
                'id' => $id,
                'voices_id' => $param['voices_id'],
                'volume' => $param['volume'],
                'speech_rate' => $param['speech_rate'],
                'pitch' => $param['pitch'],
            ];
            Redis::send($queue,$data);
        }
        return self::response();
    }
    public function getImage($request){

        $param = $request->all();

        if(empty($param['images'])){
            return self::response([], "请选择图片", 100);
        }
        $data = [
            'image_list' => json_encode($param['images'], JSON_UNESCAPED_UNICODE),
            'image_list_success' => json_encode($param['images'], JSON_UNESCAPED_UNICODE),
            'status' => $this->GoodsEnum::IMAGE_SUCCESS,
        ];
        $this->goodsDep->edit($param['platform_id'],$data);
        return self::response();

    }

}


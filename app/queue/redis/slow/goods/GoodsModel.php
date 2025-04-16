<?php

namespace app\queue\redis\slow\goods;

use app\dep\AiWorkLine\E_commerce\GoodsDep;
use app\enum\GoodsEnum;
use app\lib\AliCloud\AigcSdk;
use Webman\RedisQueue\Consumer;

class GoodsModel implements Consumer
{
    // 要消费的队列名
    public $queue = 'goods-model';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';
    public $contentId;

    // 消费
    public function consume($data)
    {
        $this->contentId = $data;
        $dep = new GoodsDep();
        $item = $dep->first($this->contentId);

        if (!$item) {
            $this->log('没找到该ID内容: ' . $this->contentId);
            return false;
        }
        $sdk = new AigcSdk();

        $this->log("Processing content ID: {$this->contentId}");

        $prompt = $item->tips;
        $chat = str_replace('{name}', $item['title'], $prompt);
        $chat = str_replace('{ocr}', $item['ocr'], $chat);

        $resChat = $sdk->chat("你是一名产品推荐官", $chat);
        $origin = $resChat['output']['choices'][0]['message']['content'];
        $this->log("AI response: {$origin}");

        $content = trim(str_replace(['```json', '```'], '', $origin));
        $contentArr = json_decode($content, true);

        if (!isset($contentArr['卖点列表'])) {
            $data = [
                'status' => GoodsEnum::POINT_ERROR,
                'status_msg' => 'AI生成不符合预期',
                'model_origin' => $origin
            ];
            $dep->edit($this->contentId, $data);
            $this->log('Content generation error', ['id' => $this->contentId]);
            return true;
        }

        $data = [
            'status' => GoodsEnum::POINT_SUCCESS,
            'point' => implode("\n",$contentArr['卖点列表']),
            'model_origin' => $origin
        ];
        $dep->edit($this->contentId, $data);

        $this->log("Processed content ID: {$this->contentId}");
        return true;

    }

    public function onConsumeFailure(\Throwable $e, $package)
    {
        $dep = new GoodsDep();
        $data = [
            'status' => GoodsEnum::POINT_ERROR,
            'status_msg' => $e->getMessage(),
        ];

        $dep->edit($this->contentId, $data);
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue); // 获取 Logger 实例
        $logger->info($msg, $context);
    }



}

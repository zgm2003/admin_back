<?php

namespace app\queue\redis\slow\goods;

use app\dep\AiWorkLine\E_commerce\GoodsDep;
use app\enum\GoodsEnum;
use app\lib\OcrSdk;
use Webman\RedisQueue\Consumer;

class GoodsOcr implements Consumer
{
    // 要消费的队列名
    public $queue = 'goods-ocr';

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
            $this->log('No content found with ID: ' . $this->contentId);
            return false;
        }
        $sdk = new OcrSdk();
        $this->log("Processing content ID: {$this->contentId}");
        $res = $sdk->ocrList(json_decode($item['image_list_success'], true)); // 这里传入的是数组
        if ($res['code'] == 200) {
            $ocrTexts = [];
            $errorMessages = [];

            foreach ($res['results'] as $result) {
                if (isset($result['code']) && $result['code'] == 200) {
                    $ocrTexts[] = $result['text']; // 识别成功的文本
                } else {
                    $errorMessages[] = isset($result['error']) ? $result['error'] : '未知错误';
                }
            }

            if (!empty($ocrTexts)) {
                // OCR 识别成功，合并文本
                $data = [
                    'ocr' => implode(' ', $ocrTexts),
                    'status' => GoodsEnum::REVIEW,
                ];
            } else {
                // 如果所有 OCR 识别都失败，则存储错误信息
                $data = [
                    'status' => GoodsEnum::OCR_ERROR,
                    'status_msg' => implode(' | ', $errorMessages),
                ];
            }
        } else {
            $data = [
                'status' => GoodsEnum::OCR_ERROR,
                'status_msg' => 'OCR 处理失败，未知错误',
            ];
        }
        // 更新数据库
        $dep->edit($this->contentId, $data);
        $this->log("Processed content ID: {$this->contentId}");
    }
    public function onConsumeFailure(\Throwable $e, $package)
    {
        $dep = new GoodsDep();
        $data = [
            'status' => GoodsEnum::OCR_ERROR,
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

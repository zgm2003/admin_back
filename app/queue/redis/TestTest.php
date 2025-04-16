<?php

namespace app\queue\redis;

use app\dep\TestDep;
use Webman\RedisQueue\Consumer;

class TestTest implements Consumer
{
    // 要消费的队列名
    public $queue = 'test-test';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';
    public $contentId;

    // 消费
    public function consume($data)
    {
        $this->contentId = $data;
        $data1 = [
            'id' => $this->contentId,
            'code' => 200,
            'msg' => 'success',
        ];
        $this->log('test-test success');



//        $dep = new TestDep();
//        $item = $dep->first($this->contentId);
//        if (!$item) {
//            $this->log('No content found with ID: ' . $this->contentId);
//            return false;
//        }
    }
    public function onConsumeFailure(\Throwable $e, $package)
    {
        $this->log('test-test error', $e->getMessage());


    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
            
}

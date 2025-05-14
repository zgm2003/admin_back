<?php

namespace app\queue\redis\fast;

use Webman\RedisQueue\Consumer;

class TestTest implements Consumer
{
    // 要消费的队列名
    public $queue = 'test-test';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';


    // 消费
    public function consume($data)
    {

    }
    public function onConsumeFailure(\Throwable $e, $package)
    {
        $this->log('operation-log error', $e->getMessage());


    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
            
}

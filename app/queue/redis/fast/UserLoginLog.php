<?php

namespace app\queue\redis\fast;

use app\dep\User\UsersLoginLogDep;
use Webman\RedisQueue\Consumer;

class UserLoginLog implements Consumer
{
    // 要消费的队列名
    public $queue = 'user-login-log';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';

    public $UsersLoginLogDep;

    public function __construct()
    {
        $this->UsersLoginLogDep = new UsersLoginLogDep();
    }

    // 消费
    public function consume($data)
    {
        try {
            $this->UsersLoginLogDep->add($data);
        } catch (\Throwable $e) {
            $this->log('insert error', ['error' => $e->getMessage(), 'data' => $data]);
        }
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

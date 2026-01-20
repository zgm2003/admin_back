<?php

namespace app\queue\redis\slow;

use app\lib\TenCentCloud\EmailSdk;
use Webman\RedisQueue\Consumer;

class EmailSend implements Consumer
{
    // 要消费的队列名
    public $queue = 'email_send';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';
    public $email;
    public $theme;
    public $code;

    // 消费
    public function consume($data)
    {
        $this->email = $data['email'];
        $this->theme = $data['theme'];
        $this->code = $data['code'];
        $sdk = new EmailSdk();
        $sdk->email($this->email, $this->theme, $this->code);
        $this->log('Success',$data);
    }
    public function onConsumeFailure(\Throwable $e, $package)
    {
        $this->log('Error', $e->getMessage());
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
            
}

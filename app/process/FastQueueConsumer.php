<?php
namespace app\process;

use PhpAmqpLib\Message\AMQPMessage;
use Workerman\Worker;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Workerman\Timer;

class FastQueueConsumer
{
    public function onWorkerStart(Worker $worker)
    {
        $config = config('rabbitmq');
        $connection = new AMQPStreamConnection(
            $config['host'], $config['port'],
            $config['user'], $config['password'], $config['vhost']
        );
        $channel = $connection->channel();
        $channel->queue_declare('fast_queue', false, true, false, false);

        $callback = function (AMQPMessage $msg) {
            echo "[FastQueue] 收到：{$msg->body}\n";
            // 快速任务，比如写日志
        };

        $channel->basic_consume('fast_queue', '', false, true, false, false, $callback);

        Timer::add(0.1, fn() => $channel->wait(null, true));
    }
}

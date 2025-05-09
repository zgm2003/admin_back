<?php
namespace app\process;

use Workerman\Worker;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Workerman\Timer;

class SlowQueueConsumer
{
    public function onWorkerStart(Worker $worker)
    {
        $config = config('rabbitmq');
        $connection = new AMQPStreamConnection(
            $config['host'], $config['port'],
            $config['user'], $config['password'], $config['vhost']
        );
        $channel = $connection->channel();
        $channel->queue_declare('slow_queue', false, true, false, false);

        $callback = function ($msg) {
            echo "[SlowQueue] 收到：{$msg->body}\n";
            // 模拟慢任务
            sleep(3);
            echo "[SlowQueue] 处理完成\n";
        };

        $channel->basic_consume('slow_queue', '', false, true, false, false, $callback);

        Timer::add(0.1, fn() => $channel->wait(null, true));
    }
}

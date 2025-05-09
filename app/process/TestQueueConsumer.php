<?php

namespace app\process;

use PhpAmqpLib\Message\AMQPMessage;
use Workerman\Worker;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Workerman\Timer;

class TestQueueConsumer
{
    public function onWorkerStart(Worker $worker)
    {
        $config = config('rabbitmq');
        $connection = new AMQPStreamConnection(
            $config['host'], $config['port'],
            $config['user'], $config['password'], $config['vhost']
        );
        $channel = $connection->channel();
        $channel->queue_declare('test_queue', false, true, false, false);

        $callback = function (AMQPMessage $msg) {
            try {
                // 1. 业务处理
                $this->consume($msg);

                // 2. 手动确认
                $msg->ack();               // ✔️ 正式 ack，告诉 RabbitMQ 可以删除该消息
            } catch (\Throwable $e) {
                // 3. 处理失败，negatively acknowledge
                $msg->nack();              // 可选：重新入队或丢弃，具体取决于服务器端设置
                $this->log('Error', ['error' => $e->getMessage()]);
            }
        };


        $channel->basic_consume('test_queue', '', false, false, false, false, $callback);

        // 可选：限流，每次只推一条消息给消费者
        $channel->basic_qos(null, 1, null);

        Timer::add(0.1, fn() => $channel->wait(null, true));
    }

    // 消费并处理业务逻辑
    public function consume(AMQPMessage $msg)
    {
        $data = json_decode($msg->body, true);

        if ($data) {
            // 发送邮件的逻辑
            $id = $data['id'];
            $abc = $data['abc'];
            $this->log('TEST  QUEUE START', ['id' => $id]);
            $this->log('TEST  QUEUE START', ['abc' => $abc]);
            $this->log('TEST  QUEUE END', ['id' => $id]);

        }
    }


    private function log($msg, $context = [])
    {
        $logger = log_daily("rabbit-queue-test"); // 获取日志实例
        $logger->info($msg, $context);
    }
}

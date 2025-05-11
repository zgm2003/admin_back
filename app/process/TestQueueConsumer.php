<?php

namespace app\process;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Workerman\Worker;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Workerman\Timer;

class TestQueueConsumer
{
    private const MAX_RETRIES = 3; // 最大重试次数
    private const DEAD_LETTER_EXCHANGE = 'dlx.test_queue'; // 死信交换机
    private const DEAD_LETTER_QUEUE = 'dlq.test_queue'; // 死信队列
    private const MAIN_QUEUE = 'test_queue'; // 主队列名称

    public function onWorkerStart(Worker $worker)
    {
        $config = config('rabbitmq');
        $connection = new AMQPStreamConnection(
            $config['host'], $config['port'],
            $config['user'], $config['password'], $config['vhost']
        );
        $channel = $connection->channel();


        // 声明死信交换机
        $channel->exchange_declare(self::DEAD_LETTER_EXCHANGE, 'direct', false, true, false);
        
        // 声明死信队列
        $channel->queue_declare(self::DEAD_LETTER_QUEUE, false, true, false, false);
        $channel->queue_bind(self::DEAD_LETTER_QUEUE, self::DEAD_LETTER_EXCHANGE, self::MAIN_QUEUE);

        // 声明主队列，并设置死信交换机
        $args = new AMQPTable([
            'x-dead-letter-exchange' => self::DEAD_LETTER_EXCHANGE,
            'x-dead-letter-routing-key' => self::MAIN_QUEUE,
            'x-message-ttl' => 60000, // 消息TTL 60秒
        ]);
        
        $channel->queue_declare(self::MAIN_QUEUE, false, true, false, false, false, $args);

        $callback = function (AMQPMessage $msg) {
            try {
                // 获取消息的重试次数
                $properties = $msg->get_properties();
                $headers = $properties['application_headers'] ?? null;
                $retryCount = 0;
                
                if ($headers instanceof AMQPTable) {
                    $retryCount = $headers->getNativeData()['x-retry-count'] ?? 0;
                }
                
                if ($retryCount >= self::MAX_RETRIES) {
                    // 超过最大重试次数，直接拒绝消息并进入死信队列
                    $this->log('Message exceeded max retries', [
                        'body' => $msg->body,
                        'retry_count' => $retryCount
                    ]);
                    $msg->reject(false);
                    return;
                }

                // 1. 业务处理
                $this->consume($msg);

                // 2. 手动确认
                $msg->ack();
            } catch (\Throwable $e) {
                // 3. 处理失败，增加重试次数并重新入队
                $retryCount++;
                $this->log('Error processing message', [
                    'error' => $e->getMessage(),
                    'retry_count' => $retryCount,
                    'body' => $msg->body
                ]);

                // 创建新的消息，保持原有属性并更新重试次数
                $properties = $msg->get_properties();
                $properties['application_headers'] = new AMQPTable(['x-retry-count' => $retryCount]);
                
                $newMsg = new AMQPMessage($msg->body, $properties);
                
                // 发布新消息
                $msg->getChannel()->basic_publish($newMsg, '', self::MAIN_QUEUE);
                
                // 确认原消息
                $msg->ack();
            }
        };

        $channel->basic_consume(self::MAIN_QUEUE, '', false, false, false, false, $callback);
        $channel->basic_qos(null, 1, null);

        Timer::add(0.1, fn() => $channel->wait(null, true));
    }

    // 消费并处理业务逻辑
    public function consume(AMQPMessage $msg)
    {
        $data = json_decode($msg->body, true);

        if ($data) {
            $id = $data['id'];
            $abc = $data['abc'];
            $this->log('TEST QUEUE START', ['id' => $id]);
            $this->log('TEST QUEUE START', ['abc' => $abc]);
            $this->log('TEST QUEUE END', ['id' => $id]);
        }
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("rabbit-queue-test");
        $logger->info($msg, $context);
    }
}

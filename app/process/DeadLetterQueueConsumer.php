<?php

namespace app\process;

use PhpAmqpLib\Message\AMQPMessage;
use Workerman\Worker;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Workerman\Timer;

class DeadLetterQueueConsumer
{
    private const DEAD_LETTER_EXCHANGE = 'dlx.test_queue';
    private const DEAD_LETTER_QUEUE = 'dlq.test_queue';

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
        $channel->queue_bind(self::DEAD_LETTER_QUEUE, self::DEAD_LETTER_EXCHANGE, 'test_queue');

        $callback = function (AMQPMessage $msg) {
            try {
                // 获取消息的原始信息
                $properties = $msg->get_properties();
                $headers = $properties['application_headers'] ?? null;
                $retryCount = 0;
                
                if ($headers instanceof \PhpAmqpLib\Wire\AMQPTable) {
                    $retryCount = $headers->getNativeData()['x-retry-count'] ?? 0;
                }

                // 记录死信消息的详细信息
                $this->log('Processing dead letter message', [
                    'body' => $msg->body,
                    'retry_count' => $retryCount,
                    'error' => 'Message exceeded max retries or TTL',
                    'original_queue' => 'test_queue',
                    'headers' => $properties
                ]);

                // 这里可以添加你的死信处理逻辑
                // 例如：
                // 1. 发送告警通知
                // 2. 记录到专门的错误日志
                // 3. 存储到数据库
                // 4. 发送到其他系统处理
                
                // 确认消息
                $msg->ack();
            } catch (\Throwable $e) {
                $this->log('Error processing dead letter message', [
                    'error' => $e->getMessage(),
                    'body' => $msg->body
                ]);
                // 死信队列的消息处理失败，直接拒绝
                $msg->reject(false);
            }
        };

        $channel->basic_consume(self::DEAD_LETTER_QUEUE, '', false, false, false, false, $callback);
        $channel->basic_qos(null, 1, null);

        Timer::add(0.1, fn() => $channel->wait(null, true));
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("rabbit-queue-dead-letter");
        $logger->info($msg, $context);
    }
} 
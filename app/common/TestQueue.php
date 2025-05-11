<?php
namespace app\common;

use PhpAmqpLib\Wire\AMQPTable;

class TestQueue extends RabbitMQ
{
    public const DEAD_LETTER_EXCHANGE = 'dlx.test_queue';
    public const DEAD_LETTER_QUEUE = 'dlq.test_queue';
    public const MAIN_QUEUE = 'test_queue';

    public function send(string $exchange, string $routingKey, string $message, array $properties = [])
    {
        // 声明死信交换机
        $this->declareExchange(self::DEAD_LETTER_EXCHANGE);
        
        // 声明主队列，使用与消费者相同的参数
        $args = new AMQPTable([
            'x-dead-letter-exchange' => self::DEAD_LETTER_EXCHANGE,
            'x-dead-letter-routing-key' => self::MAIN_QUEUE,
            'x-message-ttl' => 60000, // 消息TTL 60秒
        ]);
        
        $this->declareQueue(self::MAIN_QUEUE, false, true, false, false, false, $args);
        
        // 合并默认属性和传入的属性
        $defaultProperties = [
            'delivery_mode' => 2, // 持久化消息
            'application_headers' => new AMQPTable(['x-retry-count' => 0]) // 初始化重试次数
        ];
        
        $properties = array_merge($defaultProperties, $properties);
        
        parent::send($exchange, $routingKey, $message, $properties);
    }
} 
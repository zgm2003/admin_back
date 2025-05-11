<?php
namespace app\common;

class SimpleQueue extends RabbitMQ
{
    private const QUEUE_NAME = 'simple_queue';

    public function send(string $exchange, string $routingKey, string $message, array $properties = [])
    {
        // 声明简单队列
        $this->declareQueue(self::QUEUE_NAME);
        
        // 合并默认属性和传入的属性
        $defaultProperties = [
            'delivery_mode' => 2, // 持久化消息
        ];
        
        $properties = array_merge($defaultProperties, $properties);
        
        parent::send($exchange, $routingKey, $message, $properties);
    }
} 
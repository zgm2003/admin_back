<?php
namespace app\common;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQ
{
    protected $channel;
    protected $connection;
    private const DEAD_LETTER_EXCHANGE = 'dlx.test_queue';
    private const MAIN_QUEUE = 'test_queue';

    public function __construct(array $config)
    {
        $this->connection = new AMQPStreamConnection(
            $config['host'], $config['port'],
            $config['user'], $config['password'], $config['vhost']
        );
        $this->channel = $this->connection->channel();
    }

    public function send(string $queue, string $message)
    {
        // 声明死信交换机
        $this->channel->exchange_declare(self::DEAD_LETTER_EXCHANGE, 'direct', false, true, false);
        
        // 声明主队列，使用与消费者相同的参数
        $args = new AMQPTable([
            'x-dead-letter-exchange' => self::DEAD_LETTER_EXCHANGE,
            'x-dead-letter-routing-key' => self::MAIN_QUEUE,
            'x-message-ttl' => 60000, // 消息TTL 60秒
        ]);
        
        $this->channel->queue_declare($queue, false, true, false, false, false, $args);
        
        // 发送消息
        $msg = new AMQPMessage($message, [
            'delivery_mode' => 2, // 持久化消息
            'application_headers' => new AMQPTable(['x-retry-count' => 0]) // 初始化重试次数
        ]);
        $this->channel->basic_publish($msg, '', $queue);
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}

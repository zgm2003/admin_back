<?php
namespace app\common;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQ
{
    protected $channel;
    protected $connection;

    public function __construct(array $config)
    {
        $this->connection = new AMQPStreamConnection(
            $config['host'], $config['port'],
            $config['user'], $config['password'], $config['vhost']
        );
        $this->channel = $this->connection->channel();
    }

    /**
     * 声明交换机
     */
    protected function declareExchange(string $exchange, string $type = 'direct', bool $passive = false, bool $durable = true, bool $autoDelete = false)
    {
        $this->channel->exchange_declare($exchange, $type, $passive, $durable, $autoDelete);
    }

    /**
     * 声明队列
     */
    protected function declareQueue(string $queue, bool $passive = false, bool $durable = true, bool $exclusive = false, bool $autoDelete = false, bool $nowait = false, $arguments = null)
    {
        return $this->channel->queue_declare($queue, $passive, $durable, $exclusive, $autoDelete, $nowait, $arguments);
    }

    /**
     * 绑定队列到交换机
     */
    protected function bindQueue(string $queue, string $exchange, string $routingKey = '')
    {
        $this->channel->queue_bind($queue, $exchange, $routingKey);
    }

    /**
     * 发送消息
     */
    public function send(string $exchange, string $routingKey, string $message, array $properties = [])
    {
        $msg = new AMQPMessage($message, $properties);
        $this->channel->basic_publish($msg, $exchange, $routingKey);
    }

    /**
     * 关闭连接
     */
    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}

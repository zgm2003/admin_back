<?php
namespace app\common;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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

    public function send(string $queue, string $message)
    {
        $this->channel->queue_declare($queue, false, true, false, false);
        $msg = new AMQPMessage($message, ['delivery_mode' => 2]); // 持久化消息
        $this->channel->basic_publish($msg, '', $queue);
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}

<?php

namespace app\queue\redis\fast;

use app\dep\DevTools\OperationLogDep;
use Webman\RedisQueue\Consumer;

class OperationLog implements Consumer
{
    /**
     * 要消费的队列名，需与中间件 dispatch 时的 onQueue 名称保持一致
     *
     * @var string
     */
    public $queue = 'operation_log';

    /**
     * 使用的 Redis 连接名，对应 plugin/webman/redis-queue/redis.php 中的连接配置
     *
     * @var string
     */
    public $connection = 'default';

    /**
     * 正常消费逻辑，将 $data 写入数据库
     *
     * @param mixed $data  中间件 push 到队列的数组数据
     * @return void
     */
    public function consume($data): void
    {
        // $data 应当已经是个 array，包含 user_id, action, request_data, response_data, is_success, created_at
        (new OperationLogDep())->add($data);
    }

    /**
     * 消费失败回调，可用于记录到独立日志或告警
     *
     * @param \Throwable $e
     * @param mixed      $package  原始队列内容
     * @return void
     */
    public function onConsumeFailure(\Throwable $e, $package): void
    {
        $this->log('operation_log queue consume failed', [
            'error'   => $e->getMessage(),
            'package' => $package,
        ]);
    }

    /**
     * 简易日志方法
     *
     * @param string $msg
     * @param array  $context
     */
    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
}

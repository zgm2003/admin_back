<?php

$fastConsumer = [
    'handler'     => Webman\RedisQueue\Process\Consumer::class,
    'count'       => 8, // Linux/macOS 多进程；Windows 下由下面的独立进程补足并发
    'constructor' => [
        // 消费者类目录
        'consumer_dir' => app_path() . '/queue/redis/fast'
    ]
];

$slowConsumer = [
    'handler'     => Webman\RedisQueue\Process\Consumer::class,
    'count'       => 8, // Linux/macOS 多进程；Windows 下由下面的独立进程补足并发
    'constructor' => [
        // 消费者类目录
        'consumer_dir' => app_path() . '/queue/redis/slow'
    ]
];

$processes = [
    // 消费者配置_快
    'consumer_fast'  => $fastConsumer,
    // 消费者配置_慢
    'consumer_slow'  => $slowConsumer,
];

if (DIRECTORY_SEPARATOR === '\\') {
    // Workerman 的 Windows 运行器每个 process 配置只拉起一个 PHP 进程。
    // AI 长文本 / 图片任务不能只靠一个 slow consumer 串行执行，否则多智能体对话会排队假死。
    $processes += [
        'consumer_slow_2' => array_merge($slowConsumer, ['count' => 1]),
        'consumer_slow_3' => array_merge($slowConsumer, ['count' => 1]),
        'consumer_slow_4' => array_merge($slowConsumer, ['count' => 1]),
    ];
}

return $processes;

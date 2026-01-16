<?php
return  [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => getenv('DB_CONNECTION'),
            'host'        => getenv('DB_HOST'),
            'port'        => getenv('DB_PORT'),
            'database'    => getenv('DB_DATABASE'),
            'username'    => getenv('DB_USERNAME'),
            'password'    => getenv('DB_PASSWORD'),
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_general_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool' => [ // 连接池配置
                'max_connections' => 20, // 最大连接数（原5，建议提高）
                'min_connections' => 2,  // 最小连接数
                'wait_timeout' => 3,    // 从连接池获取连接等待的最大时间，超时后会抛出异常。仅在协程环境有效
                'idle_timeout' => 60,   // 连接池中连接最大空闲时间，超时后会关闭回收，直到连接数为min_connections
                'heartbeat_interval' => 50, // 连接池心跳检测时间，单位秒，建议小于60秒
            ],
        ],
    ],
];
<?php

return [
    'access_ttl'  => 4 * 3600,       // Access Token: 4小时 (常用企业级配置)
    'refresh_ttl' => 14 * 24 * 3600, // Refresh Token: 14天 (两周)

    'policies' => [
        // 后台：admin 单通道（同平台只能一个）
        'admin' => [
            'bind_platform' => true,
            'bind_device'   => true,
            'bind_ip'       => true,
            'single_session_per_platform' => true,
        ],

        // C端：多通道（允许同平台多个设备同时在线）
        'h5' => [
            'bind_platform' => true,
            'bind_device'   => true,
            'bind_ip'       => false,
            'single_session_per_platform' => false,
        ],
        'app' => [
            'bind_platform' => true,
            'bind_device'   => true,
            'bind_ip'       => false,
            'single_session_per_platform' => false,
        ],
        'mini' => [
            'bind_platform' => true,
            'bind_device'   => true,
            'bind_ip'       => false,
            'single_session_per_platform' => false,
        ],
    ],

    'default_policy' => [
        'bind_platform' => true,
        'bind_device'   => true,
        'bind_ip'       => false,
        'single_session_per_platform' => false,
    ],
];

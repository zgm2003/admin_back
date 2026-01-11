<?php

return [
    'enable' => true,

    // 事件监听器配置
    'listener' => [
        // AI Run 事件
        'ai.run.started' => [
            [app\listener\Ai\AiRunLogListener::class, 'onStarted'],
        ],
        'ai.run.completed' => [
            [app\listener\Ai\AiRunLogListener::class, 'onCompleted'],
        ],
        'ai.run.failed' => [
            [app\listener\Ai\AiRunLogListener::class, 'onFailed'],
        ],
    ],
];

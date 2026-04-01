<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Log;
use support\Request;
use app\process\Http;

global $argv;

return [
    // 主服务（普通接口）
    'webman' => [
        'handler' => Http::class,
        'listen' => 'http://0.0.0.0:8787',
        'count' => cpu_count() * 4,
        'User' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ],
    // SSE 服务（AI 聊天等长连接）- 独立端口避免阻塞主服务
    'sse' => [
        'handler' => Http::class,
        'listen' => 'http://0.0.0.0:8788',
        'count' => cpu_count() * 2,  // SSE 连接数，根据并发用户数调整
        'User' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ],
    // File update detection and automatic reload
    'monitor' => [
        'handler' => app\process\Monitor::class,
        'reloadable' => false,
        'constructor' => [
            // Monitor these directories
            'monitorDir' => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api')),
            // Files with these suffixes will be monitored
            'monitorExtensions' => [
                'php', 'html', 'htm', 'env'
            ],
            'options' => [
                'enable_file_monitor' => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ]
        ]
    ],
    // AI Run 超时检测
    'ai_run_timeout' => [
        'handler' => app\process\AiRunTimeoutTask::class,
    ],
    // 通知任务调度器
    'notification_task_scheduler' => [
        'handler' => app\process\NotificationTaskScheduler::class,
    ],
    // 清理过期好友请求
    'clean_expired_contact_request' => [
        'handler' => app\process\CleanExpiredContactRequestTask::class,
    ],

    // ==================== 支付域定时任务（BaseCronTask，cron 表达式在 cron_task 表中）====================
    'pay_close_expired_order' => [
        'handler' => app\process\Pay\PayCloseExpiredOrderTask::class,
    ],
    'pay_sync_pending_transaction' => [
        'handler' => app\process\Pay\PaySyncPendingTransactionTask::class,
    ],
    'pay_fulfillment_retry' => [
        'handler' => app\process\Pay\PayFulfillmentRetryTask::class,
    ],
    'pay_reconcile_daily' => [
        'handler' => app\process\Pay\PayReconcileDailyTask::class,
    ],
    'pay_reconcile_execute' => [
        'handler' => app\process\Pay\PayReconcileExecuteTask::class,
    ],
];

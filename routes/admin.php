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

use app\controller;
use Webman\Route;

// 兗底预检：拦截 /api/admin 下所有 OPTIONS，避免 404/无头导致预检失败
Route::group('/api/admin', function () {
    Route::add(['OPTIONS'], '/{path:.+}', function () {
        return response('');
    });
});

Route::group('/api/admin', function () {
    //不需要认证的接口
    
    // 测试接口
    Route::post('/test', [controller\TestController::class, 'test']);

    // 插件提交商品（无需鉴权，插件直接调用）
    Route::post('/Goods/submit', [controller\Ai\GoodsController::class, 'submit']);
});

Route::group('/api/admin', function () {
    // 需要认证的接口

    //菜单管理
    Route::post('/Permission/init', [controller\Permission\PermissionController::class, 'init']);
    Route::post('/Permission/add', [controller\Permission\PermissionController::class, 'add']);
    Route::post('/Permission/edit', [controller\Permission\PermissionController::class, 'edit']);
    Route::post('/Permission/del', [controller\Permission\PermissionController::class, 'del']);
    Route::post('/Permission/list', [controller\Permission\PermissionController::class, 'list']);
    Route::post('/Permission/batchEdit', [controller\Permission\PermissionController::class, 'batchEdit']);
    Route::post('/Permission/status', [controller\Permission\PermissionController::class, 'status']);
    Route::post('/Permission/appButtonList', [controller\Permission\PermissionController::class, 'appButtonList']);
    Route::post('/Permission/appButtonAdd', [controller\Permission\PermissionController::class, 'appButtonAdd']);
    Route::post('/Permission/appButtonEdit', [controller\Permission\PermissionController::class, 'appButtonEdit']);
    Route::post('/Permission/appButtonStatus', [controller\Permission\PermissionController::class, 'appButtonStatus']);
    Route::post('/Permission/appButtonDel', [controller\Permission\PermissionController::class, 'appButtonDel']);

    //角色管理
    Route::post('/Role/init', [controller\Permission\RoleController::class, 'init']);
    Route::post('/Role/list', [controller\Permission\RoleController::class, 'list']);
    Route::post('/Role/add', [controller\Permission\RoleController::class, 'add']);
    Route::post('/Role/edit', [controller\Permission\RoleController::class, 'edit']);
    Route::post('/Role/del', [controller\Permission\RoleController::class, 'del']);
    Route::post('/Role/default', [controller\Permission\RoleController::class, 'default']);

    //用户列表管理（拆分模块）
    Route::post('/UsersList/init', [controller\User\UsersListController::class, 'init']);
    Route::post('/UsersList/edit', [controller\User\UsersListController::class, 'edit']);
    Route::post('/UsersList/del', [controller\User\UsersListController::class, 'del']);
    Route::post('/UsersList/list', [controller\User\UsersListController::class, 'list']);
    Route::post('/UsersList/batchEdit', [controller\User\UsersListController::class, 'batchEdit']);
    Route::post('/UsersList/export', [controller\User\UsersListController::class, 'export']);

    //用户会话管理
    Route::post('/UserSession/list', [controller\User\UserSessionController::class, 'list']);
    Route::post('/UserSession/stats', [controller\User\UserSessionController::class, 'stats']);
    Route::post('/UserSession/kick', [controller\User\UserSessionController::class, 'kick']);
    Route::post('/UserSession/batchKick', [controller\User\UserSessionController::class, 'batchKick']);

    //操作日志管理
    Route::post('/OperationLog/init', [controller\System\OperationLogController::class, 'init']);
    Route::post('/OperationLog/list', [controller\System\OperationLogController::class, 'list']);
    Route::post('/OperationLog/listCursor', [controller\System\OperationLogController::class, 'listCursor']);
    Route::post('/OperationLog/del', [controller\System\OperationLogController::class, 'del']);

    //用户登录日志管理
    Route::post('/UsersLoginLog/init', [controller\User\UsersLoginLogController::class, 'init']);
    Route::post('/UsersLoginLog/list', [controller\User\UsersLoginLogController::class, 'list']);
    Route::post('/UsersLoginLog/listCursor', [controller\User\UsersLoginLogController::class, 'listCursor']);

    //用户快捷入口
    Route::post('/UsersQuickEntry/add', [controller\User\UsersQuickEntryController::class, 'add']);
    Route::post('/UsersQuickEntry/del', [controller\User\UsersQuickEntryController::class, 'del']);
    Route::post('/UsersQuickEntry/sort', [controller\User\UsersQuickEntryController::class, 'sort']);

    //上传规则
    Route::post('/UploadRule/init', [controller\System\UploadRuleController::class, 'init']);
    Route::post('/UploadRule/add', [controller\System\UploadRuleController::class, 'add']);
    Route::post('/UploadRule/edit', [controller\System\UploadRuleController::class, 'edit']);
    Route::post('/UploadRule/del', [controller\System\UploadRuleController::class, 'del']);
    Route::post('/UploadRule/list', [controller\System\UploadRuleController::class, 'list']);

    //上传驱动
    Route::post('/UploadDriver/init', [controller\System\UploadDriverController::class, 'init']);
    Route::post('/UploadDriver/add', [controller\System\UploadDriverController::class, 'add']);
    Route::post('/UploadDriver/edit', [controller\System\UploadDriverController::class, 'edit']);
    Route::post('/UploadDriver/del', [controller\System\UploadDriverController::class, 'del']);
    Route::post('/UploadDriver/list', [controller\System\UploadDriverController::class, 'list']);

    //上传配置
    Route::post('/UploadSetting/init', [controller\System\UploadSettingController::class, 'init']);
    Route::post('/UploadSetting/add', [controller\System\UploadSettingController::class, 'add']);
    Route::post('/UploadSetting/edit', [controller\System\UploadSettingController::class, 'edit']);
    Route::post('/UploadSetting/del', [controller\System\UploadSettingController::class, 'del']);
    Route::post('/UploadSetting/list', [controller\System\UploadSettingController::class, 'list']);
    Route::post('/UploadSetting/status', [controller\System\UploadSettingController::class, 'status']);

    //认证平台管理
    Route::post('/AuthPlatform/init', [controller\Permission\AuthPlatformController::class, 'init']);
    Route::post('/AuthPlatform/list', [controller\Permission\AuthPlatformController::class, 'list']);
    Route::post('/AuthPlatform/add', [controller\Permission\AuthPlatformController::class, 'add']);
    Route::post('/AuthPlatform/edit', [controller\Permission\AuthPlatformController::class, 'edit']);
    Route::post('/AuthPlatform/del', [controller\Permission\AuthPlatformController::class, 'del']);
    Route::post('/AuthPlatform/status', [controller\Permission\AuthPlatformController::class, 'status']);

    //系统设置（统一接口命名）
    Route::post('/SystemSetting/init', [controller\System\SystemSettingController::class, 'init']);
    Route::post('/SystemSetting/add', [controller\System\SystemSettingController::class, 'add']);
    Route::post('/SystemSetting/edit', [controller\System\SystemSettingController::class, 'edit']);
    Route::post('/SystemSetting/del', [controller\System\SystemSettingController::class, 'del']);
    Route::post('/SystemSetting/list', [controller\System\SystemSettingController::class, 'list']);
    Route::post('/SystemSetting/status', [controller\System\SystemSettingController::class, 'status']);

    // AI 模型配置
    Route::post('/AiModels/init', [controller\Ai\AiModelsController::class, 'init']);
    Route::post('/AiModels/list', [controller\Ai\AiModelsController::class, 'list']);
    Route::post('/AiModels/add', [controller\Ai\AiModelsController::class, 'add']);
    Route::post('/AiModels/edit', [controller\Ai\AiModelsController::class, 'edit']);
    Route::post('/AiModels/del', [controller\Ai\AiModelsController::class, 'del']);
    Route::post('/AiModels/status', [controller\Ai\AiModelsController::class, 'status']);

    // AI 工具管理
    Route::post('/AiTools/init', [controller\Ai\AiToolsController::class, 'init']);
    Route::post('/AiTools/list', [controller\Ai\AiToolsController::class, 'list']);
    Route::post('/AiTools/add', [controller\Ai\AiToolsController::class, 'add']);
    Route::post('/AiTools/edit', [controller\Ai\AiToolsController::class, 'edit']);
    Route::post('/AiTools/del', [controller\Ai\AiToolsController::class, 'del']);
    Route::post('/AiTools/status', [controller\Ai\AiToolsController::class, 'status']);
    Route::post('/AiTools/bindTools', [controller\Ai\AiToolsController::class, 'bindTools']);
    Route::post('/AiTools/getAgentTools', [controller\Ai\AiToolsController::class, 'getAgentTools']);

    // AI 智能体配置
    Route::post('/AiAgents/init', [controller\Ai\AiAgentsController::class, 'init']);
    Route::post('/AiAgents/list', [controller\Ai\AiAgentsController::class, 'list']);
    Route::post('/AiAgents/add', [controller\Ai\AiAgentsController::class, 'add']);
    Route::post('/AiAgents/edit', [controller\Ai\AiAgentsController::class, 'edit']);
    Route::post('/AiAgents/del', [controller\Ai\AiAgentsController::class, 'del']);
    Route::post('/AiAgents/status', [controller\Ai\AiAgentsController::class, 'status']);

    // AI 会话管理
    Route::post('/AiConversations/list', [controller\Ai\AiConversationsController::class, 'list']);
    Route::post('/AiConversations/detail', [controller\Ai\AiConversationsController::class, 'detail']);
    Route::post('/AiConversations/add', [controller\Ai\AiConversationsController::class, 'add']);
    Route::post('/AiConversations/edit', [controller\Ai\AiConversationsController::class, 'edit']);
    Route::post('/AiConversations/del', [controller\Ai\AiConversationsController::class, 'del']);
    Route::post('/AiConversations/status', [controller\Ai\AiConversationsController::class, 'status']);

    // AI 消息管理
    Route::post('/AiMessages/list', [controller\Ai\AiMessagesController::class, 'list']);
    Route::post('/AiMessages/del', [controller\Ai\AiMessagesController::class, 'del']);
    Route::post('/AiMessages/editContent', [controller\Ai\AiMessagesController::class, 'editContent']);
    Route::post('/AiMessages/feedback', [controller\Ai\AiMessagesController::class, 'feedback']);

    // AI 对话（发送消息并获取回复）
    Route::post('/AiChat/send', [controller\Ai\AiChatController::class, 'send']);
    Route::post('/AiChat/stream', [controller\Ai\AiChatController::class, 'stream']);
    Route::post('/AiChat/cancel', [controller\Ai\AiChatController::class, 'cancel']);

    // AI 运行监控
    Route::post('/AiRuns/init', [controller\Ai\AiRunsController::class, 'init']);
    Route::post('/AiRuns/list', [controller\Ai\AiRunsController::class, 'list']);
    Route::post('/AiRuns/detail', [controller\Ai\AiRunsController::class, 'detail']);
    Route::post('/AiRuns/stats', [controller\Ai\AiRunsController::class, 'stats']);
    Route::post('/AiRuns/statsByDate', [controller\Ai\AiRunsController::class, 'statsByDate']);
    Route::post('/AiRuns/statsByAgent', [controller\Ai\AiRunsController::class, 'statsByAgent']);
    Route::post('/AiRuns/statsByUser', [controller\Ai\AiRunsController::class, 'statsByUser']);

    // AI 提示词管理
    Route::post('/AiPrompts/list', [controller\Ai\AiPromptsController::class, 'list']);
    Route::post('/AiPrompts/detail', [controller\Ai\AiPromptsController::class, 'detail']);
    Route::post('/AiPrompts/add', [controller\Ai\AiPromptsController::class, 'add']);
    Route::post('/AiPrompts/edit', [controller\Ai\AiPromptsController::class, 'edit']);
    Route::post('/AiPrompts/del', [controller\Ai\AiPromptsController::class, 'del']);
    Route::post('/AiPrompts/toggleFavorite', [controller\Ai\AiPromptsController::class, 'toggleFavorite']);
    Route::post('/AiPrompts/use', [controller\Ai\AiPromptsController::class, 'use']);

    // AI 代码生成（流式）
    Route::post('/Ai/GenAi/init', [controller\Ai\GenAiController::class, 'init']);
    Route::post('/Ai/GenAi/conversations', [controller\Ai\GenAiController::class, 'conversations']);
    Route::post('/Ai/GenAi/messages', [controller\Ai\GenAiController::class, 'messages']);
    Route::post('/Ai/GenAi/deleteConversation', [controller\Ai\GenAiController::class, 'deleteConversation']);
    Route::post('/Ai/GenAi/stream', [controller\Ai\GenAiController::class, 'stream']);

    // 队列监控
    Route::post('/QueueMonitor/list', [controller\System\QueueMonitorController::class, 'list']);
    Route::post('/QueueMonitor/failedList', [controller\System\QueueMonitorController::class, 'failedList']);
    Route::post('/QueueMonitor/retry', [controller\System\QueueMonitorController::class, 'retry']);
    Route::post('/QueueMonitor/clear', [controller\System\QueueMonitorController::class, 'clear']);
    Route::post('/QueueMonitor/clearFailed', [controller\System\QueueMonitorController::class, 'clearFailed']);

    // 导出任务管理
    Route::post('/ExportTask/statusCount', [controller\System\ExportTaskController::class, 'statusCount']);
    Route::post('/ExportTask/list', [controller\System\ExportTaskController::class, 'list']);
    Route::post('/ExportTask/del', [controller\System\ExportTaskController::class, 'del']);

    // 定时任务管理
    Route::post('/CronTask/init', [controller\System\CronTaskController::class, 'init']);
    Route::post('/CronTask/list', [controller\System\CronTaskController::class, 'list']);
    Route::post('/CronTask/add', [controller\System\CronTaskController::class, 'add']);
    Route::post('/CronTask/edit', [controller\System\CronTaskController::class, 'edit']);
    Route::post('/CronTask/del', [controller\System\CronTaskController::class, 'del']);
    Route::post('/CronTask/status', [controller\System\CronTaskController::class, 'status']);
    Route::post('/CronTask/logs', [controller\System\CronTaskController::class, 'logs']);

    // Tauri 版本管理
    Route::post('/TauriVersion/init', [controller\System\TauriVersionController::class, 'init']);
    Route::post('/TauriVersion/list', [controller\System\TauriVersionController::class, 'list']);
    Route::post('/TauriVersion/add', [controller\System\TauriVersionController::class, 'add']);
    Route::post('/TauriVersion/edit', [controller\System\TauriVersionController::class, 'edit']);
    Route::post('/TauriVersion/setLatest', [controller\System\TauriVersionController::class, 'setLatest']);
    Route::post('/TauriVersion/del', [controller\System\TauriVersionController::class, 'del']);
    Route::post('/TauriVersion/forceUpdate', [controller\System\TauriVersionController::class, 'forceUpdate']);
    Route::post('/TauriVersion/updateJson', [controller\System\TauriVersionController::class, 'updateJson']);

    // 系统日志
    Route::post('/SystemLog/init', [controller\System\SystemLogController::class, 'init']);
    Route::post('/SystemLog/files', [controller\System\SystemLogController::class, 'files']);
    Route::post('/SystemLog/content', [controller\System\SystemLogController::class, 'content']);

    // WebSocket 绑定与推送
    Route::post('/WebSocket/bind', [controller\System\WebSocketController::class, 'bind']);
    Route::post('/WebSocket/onlineCount', [controller\System\WebSocketController::class, 'onlineCount']);
    Route::post('/WebSocket/pushToUser', [controller\System\WebSocketController::class, 'pushToUser']);
    Route::post('/WebSocket/broadcast', [controller\System\WebSocketController::class, 'broadcast']);
    Route::post('/WebSocket/testPlatformPush', [controller\System\WebSocketController::class, 'testPlatformPush']);

    // 通知管理
    Route::post('/Notification/init', [controller\System\NotificationController::class, 'init']);
    Route::post('/Notification/list', [controller\System\NotificationController::class, 'list']);
    Route::post('/Notification/listCursor', [controller\System\NotificationController::class, 'listCursor']);
    Route::post('/Notification/unreadCount', [controller\System\NotificationController::class, 'unreadCount']);
    Route::post('/Notification/read', [controller\System\NotificationController::class, 'read']);
    Route::post('/Notification/del', [controller\System\NotificationController::class, 'del']);

    // 通知任务管理
    Route::post('/NotificationTask/init', [controller\System\NotificationTaskController::class, 'init']);
    Route::post('/NotificationTask/statusCount', [controller\System\NotificationTaskController::class, 'statusCount']);
    Route::post('/NotificationTask/list', [controller\System\NotificationTaskController::class, 'list']);
    Route::post('/NotificationTask/add', [controller\System\NotificationTaskController::class, 'add']);
    Route::post('/NotificationTask/del', [controller\System\NotificationTaskController::class, 'del']);
    Route::post('/NotificationTask/cancel', [controller\System\NotificationTaskController::class, 'cancel']);

    // 电商商品管理
    Route::post('/Goods/init', [controller\Ai\GoodsController::class, 'init']);
    Route::post('/Goods/statusCount', [controller\Ai\GoodsController::class, 'statusCount']);
    Route::post('/Goods/list', [controller\Ai\GoodsController::class, 'list']);
    Route::post('/Goods/add', [controller\Ai\GoodsController::class, 'add']);
    Route::post('/Goods/edit', [controller\Ai\GoodsController::class, 'edit']);
    Route::post('/Goods/del', [controller\Ai\GoodsController::class, 'del']);
    Route::post('/Goods/ocr', [controller\Ai\GoodsController::class, 'ocr']);
    Route::post('/Goods/generate', [controller\Ai\GoodsController::class, 'generate']);
    Route::post('/Goods/tts', [controller\Ai\GoodsController::class, 'tts']);

    // 聊天管理
    Route::post('/Chat/conversationList', [controller\Chat\ChatController::class, 'conversationList']);
    Route::post('/Chat/createPrivate', [controller\Chat\ChatController::class, 'createPrivate']);
    Route::post('/Chat/createGroup', [controller\Chat\ChatController::class, 'createGroup']);
    Route::post('/Chat/deleteConversation', [controller\Chat\ChatController::class, 'deleteConversation']);
    Route::post('/Chat/groupInfo', [controller\Chat\ChatController::class, 'groupInfo']);
    Route::post('/Chat/groupUpdate', [controller\Chat\ChatController::class, 'groupUpdate']);
    Route::post('/Chat/groupInvite', [controller\Chat\ChatController::class, 'groupInvite']);
    Route::post('/Chat/groupKick', [controller\Chat\ChatController::class, 'groupKick']);
    Route::post('/Chat/groupLeave', [controller\Chat\ChatController::class, 'groupLeave']);
    Route::post('/Chat/groupTransfer', [controller\Chat\ChatController::class, 'groupTransfer']);
    Route::post('/Chat/sendMessage', [controller\Chat\ChatController::class, 'sendMessage']);
    Route::post('/Chat/messageList', [controller\Chat\ChatController::class, 'messageList']);
    Route::post('/Chat/markRead', [controller\Chat\ChatController::class, 'markRead']);
    Route::post('/Chat/contactList', [controller\Chat\ChatController::class, 'contactList']);
    Route::post('/Chat/contactAdd', [controller\Chat\ChatController::class, 'contactAdd']);
    Route::post('/Chat/contactConfirm', [controller\Chat\ChatController::class, 'contactConfirm']);
    Route::post('/Chat/contactDelete', [controller\Chat\ChatController::class, 'contactDelete']);
    Route::post('/Chat/togglePin', [controller\Chat\ChatController::class, 'togglePin']);
    Route::post('/Chat/typing', [controller\Chat\ChatController::class, 'typing']);
    Route::post('/Chat/onlineStatus', [controller\Chat\ChatController::class, 'onlineStatus']);
    Route::post('/Chat/recallMessage', [controller\Chat\ChatController::class, 'recallMessage']);
    Route::post('/Chat/setAdmin', [controller\Chat\ChatController::class, 'setAdmin']);

    // ==================== 支付管理 ====================
    // 支付渠道
    Route::post('/PayChannel/init', [controller\Pay\PayChannelController::class, 'init']);
    Route::post('/PayChannel/list', [controller\Pay\PayChannelController::class, 'list']);
    Route::post('/PayChannel/add', [controller\Pay\PayChannelController::class, 'add']);
    Route::post('/PayChannel/edit', [controller\Pay\PayChannelController::class, 'edit']);
    Route::post('/PayChannel/del', [controller\Pay\PayChannelController::class, 'del']);
    Route::post('/PayChannel/status', [controller\Pay\PayChannelController::class, 'status']);

    // 用户端支付接口
    Route::post('/pay/recharge', [controller\Pay\OrderController::class, 'recharge']);
    Route::post('/pay/createPay', [controller\Pay\OrderController::class, 'createPay']);
    Route::post('/pay/cancelOrder', [controller\Pay\OrderController::class, 'cancelOrder']);
    Route::post('/pay/queryResult', [controller\Pay\OrderController::class, 'queryResult']);
    Route::post('/pay/orderDetail', [controller\Pay\OrderController::class, 'orderDetail']);
    Route::post('/pay/walletInfo', [controller\Pay\OrderController::class, 'walletInfo']);
    Route::post('/pay/walletBills', [controller\Pay\OrderController::class, 'walletBills']);
    Route::post('/pay/myOrders', [controller\Pay\OrderController::class, 'myOrders']);
    
    // 订单管理
    Route::post('/PayOrder/init', [controller\Pay\OrderController::class, 'init']);
    Route::post('/PayOrder/list', [controller\Pay\OrderController::class, 'list']);
    Route::post('/PayOrder/detail', [controller\Pay\OrderController::class, 'detail']);
    Route::post('/PayOrder/statusCount', [controller\Pay\OrderController::class, 'statusCount']);
    Route::post('/PayOrder/close', [controller\Pay\OrderController::class, 'close']);
    Route::post('/PayOrder/remark', [controller\Pay\OrderController::class, 'remark']);

    // 支付流水
    Route::post('/PayTransaction/init', [controller\Pay\PayTransactionController::class, 'init']);
    Route::post('/PayTransaction/list', [controller\Pay\PayTransactionController::class, 'list']);
    Route::post('/PayTransaction/detail', [controller\Pay\PayTransactionController::class, 'detail']);

    // 退款管理
    Route::post('/PayRefund/init', [controller\Pay\PayRefundController::class, 'init']);
    Route::post('/PayRefund/list', [controller\Pay\PayRefundController::class, 'list']);
    Route::post('/PayRefund/detail', [controller\Pay\PayRefundController::class, 'detail']);
    Route::post('/PayRefund/apply', [controller\Pay\PayRefundController::class, 'apply']);

    // 钱包管理
    Route::post('/UserWallet/init', [controller\Pay\UserWalletController::class, 'init']);
    Route::post('/UserWallet/list', [controller\Pay\UserWalletController::class, 'list']);
    Route::post('/UserWallet/transactions', [controller\Pay\UserWalletController::class, 'transactions']);
    Route::post('/UserWallet/adjust', [controller\Pay\UserWalletController::class, 'adjust']);

    // 对账管理
    Route::post('/PayReconcile/init', [controller\Pay\PayReconcileController::class, 'init']);
    Route::post('/PayReconcile/list', [controller\Pay\PayReconcileController::class, 'list']);
    Route::post('/PayReconcile/detail', [controller\Pay\PayReconcileController::class, 'detail']);
    Route::post('/PayReconcile/retry', [controller\Pay\PayReconcileController::class, 'retry']);

    // 支付回调（无需 CheckPermission / OperationLog）
})->middleware([
    app\middleware\CheckToken::class,
    app\middleware\CheckPermission::class,
    app\middleware\OperationLog::class,
]);

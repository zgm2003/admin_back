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
    Route::post('/DevTools/OperationLog/init', [controller\DevTools\OperationLogController::class, 'init']);
    Route::post('/DevTools/OperationLog/list', [controller\DevTools\OperationLogController::class, 'list']);
    Route::post('/DevTools/OperationLog/listCursor', [controller\DevTools\OperationLogController::class, 'listCursor']);
    Route::post('/DevTools/OperationLog/del', [controller\DevTools\OperationLogController::class, 'del']);

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
    Route::post('/AuthPlatform/init', [controller\System\AuthPlatformController::class, 'init']);
    Route::post('/AuthPlatform/list', [controller\System\AuthPlatformController::class, 'list']);
    Route::post('/AuthPlatform/add', [controller\System\AuthPlatformController::class, 'add']);
    Route::post('/AuthPlatform/edit', [controller\System\AuthPlatformController::class, 'edit']);
    Route::post('/AuthPlatform/del', [controller\System\AuthPlatformController::class, 'del']);
    Route::post('/AuthPlatform/status', [controller\System\AuthPlatformController::class, 'status']);

    //系统设置（统一接口命名）
    Route::post('/SystemSetting/init', [controller\System\SystemSettingController::class, 'init']);
    Route::post('/SystemSetting/add', [controller\System\SystemSettingController::class, 'add']);
    Route::post('/SystemSetting/edit', [controller\System\SystemSettingController::class, 'edit']);
    Route::post('/SystemSetting/del', [controller\System\SystemSettingController::class, 'del']);
    Route::post('/SystemSetting/list', [controller\System\SystemSettingController::class, 'list']);
    Route::post('/SystemSetting/status', [controller\System\SystemSettingController::class, 'status']);

    // Test - 测试
    Route::post('/System/Test/init', [controller\System\TestController::class, 'init']);
    Route::post('/System/Test/list', [controller\System\TestController::class, 'list']);
    Route::post('/System/Test/add', [controller\System\TestController::class, 'add']);
    Route::post('/System/Test/edit', [controller\System\TestController::class, 'edit']);
    Route::post('/System/Test/del', [controller\System\TestController::class, 'del']);

    // AI 模型配置
    Route::post('/AiModel/init', [controller\Ai\AiModelController::class, 'init']);
    Route::post('/AiModel/list', [controller\Ai\AiModelController::class, 'list']);
    Route::post('/AiModel/add', [controller\Ai\AiModelController::class, 'add']);
    Route::post('/AiModel/edit', [controller\Ai\AiModelController::class, 'edit']);
    Route::post('/AiModel/del', [controller\Ai\AiModelController::class, 'del']);
    Route::post('/AiModel/status', [controller\Ai\AiModelController::class, 'status']);

    // AI 智能体配置
    Route::post('/AiAgent/init', [controller\Ai\AiAgentController::class, 'init']);
    Route::post('/AiAgent/list', [controller\Ai\AiAgentController::class, 'list']);
    Route::post('/AiAgent/add', [controller\Ai\AiAgentController::class, 'add']);
    Route::post('/AiAgent/edit', [controller\Ai\AiAgentController::class, 'edit']);
    Route::post('/AiAgent/del', [controller\Ai\AiAgentController::class, 'del']);
    Route::post('/AiAgent/status', [controller\Ai\AiAgentController::class, 'status']);

    // AI 会话管理
    Route::post('/AiConversation/list', [controller\Ai\AiConversationController::class, 'list']);
    Route::post('/AiConversation/detail', [controller\Ai\AiConversationController::class, 'detail']);
    Route::post('/AiConversation/add', [controller\Ai\AiConversationController::class, 'add']);
    Route::post('/AiConversation/edit', [controller\Ai\AiConversationController::class, 'edit']);
    Route::post('/AiConversation/del', [controller\Ai\AiConversationController::class, 'del']);
    Route::post('/AiConversation/status', [controller\Ai\AiConversationController::class, 'status']);

    // AI 消息管理
    Route::post('/AiMessage/list', [controller\Ai\AiMessageController::class, 'list']);
    Route::post('/AiMessage/del', [controller\Ai\AiMessageController::class, 'del']);
    Route::post('/AiMessage/editContent', [controller\Ai\AiMessageController::class, 'editContent']);
    Route::post('/AiMessage/feedback', [controller\Ai\AiMessageController::class, 'feedback']);

    // AI 对话（发送消息并获取回复）
    Route::post('/AiChat/send', [controller\Ai\AiChatController::class, 'send']);
    Route::post('/AiChat/stream', [controller\Ai\AiChatController::class, 'stream']);
    Route::post('/AiChat/cancel', [controller\Ai\AiChatController::class, 'cancel']);

    // AI 运行监控
    Route::post('/AiRun/init', [controller\Ai\AiRunController::class, 'init']);
    Route::post('/AiRun/list', [controller\Ai\AiRunController::class, 'list']);
    Route::post('/AiRun/detail', [controller\Ai\AiRunController::class, 'detail']);
    Route::post('/AiRun/stats', [controller\Ai\AiRunController::class, 'stats']);
    Route::post('/AiRun/statsByDate', [controller\Ai\AiRunController::class, 'statsByDate']);
    Route::post('/AiRun/statsByAgent', [controller\Ai\AiRunController::class, 'statsByAgent']);
    Route::post('/AiRun/statsByUser', [controller\Ai\AiRunController::class, 'statsByUser']);

    // AI 提示词管理
    Route::post('/AiPrompt/list', [controller\Ai\AiPromptController::class, 'list']);
    Route::post('/AiPrompt/detail', [controller\Ai\AiPromptController::class, 'detail']);
    Route::post('/AiPrompt/add', [controller\Ai\AiPromptController::class, 'add']);
    Route::post('/AiPrompt/edit', [controller\Ai\AiPromptController::class, 'edit']);
    Route::post('/AiPrompt/del', [controller\Ai\AiPromptController::class, 'del']);
    Route::post('/AiPrompt/toggleFavorite', [controller\Ai\AiPromptController::class, 'toggleFavorite']);
    Route::post('/AiPrompt/use', [controller\Ai\AiPromptController::class, 'use']);

    // 代码生成器
    Route::post('/DevTools/Gen/tables', [controller\DevTools\GenController::class, 'tables']);
    Route::post('/DevTools/Gen/columns', [controller\DevTools\GenController::class, 'columns']);
    Route::post('/DevTools/Gen/preview', [controller\DevTools\GenController::class, 'preview']);
    Route::post('/DevTools/Gen/generate', [controller\DevTools\GenController::class, 'generate']);

    // 队列监控
    Route::post('/DevTools/QueueMonitor/list', [controller\DevTools\QueueMonitorController::class, 'list']);
    Route::post('/DevTools/QueueMonitor/failedList', [controller\DevTools\QueueMonitorController::class, 'failedList']);
    Route::post('/DevTools/QueueMonitor/retry', [controller\DevTools\QueueMonitorController::class, 'retry']);
    Route::post('/DevTools/QueueMonitor/clear', [controller\DevTools\QueueMonitorController::class, 'clear']);
    Route::post('/DevTools/QueueMonitor/clearFailed', [controller\DevTools\QueueMonitorController::class, 'clearFailed']);

    // 导出任务管理
    Route::post('/DevTools/ExportTask/statusCount', [controller\DevTools\ExportTaskController::class, 'statusCount']);
    Route::post('/DevTools/ExportTask/list', [controller\DevTools\ExportTaskController::class, 'list']);
    Route::post('/DevTools/ExportTask/del', [controller\DevTools\ExportTaskController::class, 'del']);
    Route::post('/DevTools/ExportTask/batchDel', [controller\DevTools\ExportTaskController::class, 'batchDel']);

    // 定时任务管理
    Route::post('/DevTools/CronTask/init', [controller\DevTools\CronTaskController::class, 'init']);
    Route::post('/DevTools/CronTask/list', [controller\DevTools\CronTaskController::class, 'list']);
    Route::post('/DevTools/CronTask/add', [controller\DevTools\CronTaskController::class, 'add']);
    Route::post('/DevTools/CronTask/edit', [controller\DevTools\CronTaskController::class, 'edit']);
    Route::post('/DevTools/CronTask/del', [controller\DevTools\CronTaskController::class, 'del']);
    Route::post('/DevTools/CronTask/status', [controller\DevTools\CronTaskController::class, 'status']);
    Route::post('/DevTools/CronTask/logs', [controller\DevTools\CronTaskController::class, 'logs']);

    // Tauri 版本管理
    Route::post('/DevTools/TauriVersion/init', [controller\DevTools\TauriVersionController::class, 'init']);
    Route::post('/DevTools/TauriVersion/list', [controller\DevTools\TauriVersionController::class, 'list']);
    Route::post('/DevTools/TauriVersion/add', [controller\DevTools\TauriVersionController::class, 'add']);
    Route::post('/DevTools/TauriVersion/edit', [controller\DevTools\TauriVersionController::class, 'edit']);
    Route::post('/DevTools/TauriVersion/setLatest', [controller\DevTools\TauriVersionController::class, 'setLatest']);
    Route::post('/DevTools/TauriVersion/del', [controller\DevTools\TauriVersionController::class, 'del']);
    Route::post('/DevTools/TauriVersion/forceUpdate', [controller\DevTools\TauriVersionController::class, 'forceUpdate']);
    Route::post('/DevTools/TauriVersion/updateJson', [controller\DevTools\TauriVersionController::class, 'updateJson']);

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
    Route::post('/Chat/typing', [controller\Chat\ChatController::class, 'typing']);
    Route::post('/Chat/onlineStatus', [controller\Chat\ChatController::class, 'onlineStatus']);
})->middleware([
    app\middleware\CheckToken::class,
    app\middleware\CheckPermission::class,
    app\middleware\OperationLog::class,
]);

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

// 兜底预检：拦截 /api/admin 下所有 OPTIONS，避免 404/无头导致预检失败
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
    Route::post('/OperationLog/del', [controller\System\OperationLogController::class, 'del']);

    //用户登录日志管理
    Route::post('/UsersLoginLog/init', [controller\User\UsersLoginLogController::class, 'init']);
    Route::post('/UsersLoginLog/list', [controller\User\UsersLoginLogController::class, 'list']);


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

    // 代码生成器
    Route::post('/DevTools/Gen/tables', [controller\DevTools\GenController::class, 'tables']);
    Route::post('/DevTools/Gen/columns', [controller\DevTools\GenController::class, 'columns']);
    Route::post('/DevTools/Gen/preview', [controller\DevTools\GenController::class, 'preview']);
    Route::post('/DevTools/Gen/generate', [controller\DevTools\GenController::class, 'generate']);

    // WebSocket 绑定与推送
    Route::post('/WebSocket/bind', [controller\System\WebSocketController::class, 'bind']);
    Route::post('/WebSocket/onlineCount', [controller\System\WebSocketController::class, 'onlineCount']);
    Route::post('/WebSocket/pushToUser', [controller\System\WebSocketController::class, 'pushToUser']);
    Route::post('/WebSocket/broadcast', [controller\System\WebSocketController::class, 'broadcast']);
})->middleware([
    app\middleware\CheckToken::class,
    app\middleware\CheckPermission::class,
    app\middleware\OperationLog::class,
]);

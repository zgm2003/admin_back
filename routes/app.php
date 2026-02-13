<?php
/**
 * APP/H5 专用路由
 * 按钮权限由 @Permission 注解控制
 */

use app\controller;
use Webman\Route;

// 兆底预检：拦截 /api/app 下所有 OPTIONS，避免 404/无头导致预检失败
Route::group('/api/app', function () {
    Route::add(['OPTIONS'], '/{path:.+}', function () {
        return response('');
    });
});


Route::group('/api/app', function () {
    // WebSocket 绑定
    Route::post('/WebSocket/bind', [controller\System\WebSocketController::class, 'bind']);
    
    // 测试按钮权限
    Route::post('/test', [controller\App\AppController::class, 'test']);

    // 通知中心（APP/H5）
    Route::post('/Notification/list', [controller\System\NotificationController::class, 'listCursor']);
    Route::post('/Notification/unreadCount', [controller\System\NotificationController::class, 'unreadCount']);
    Route::post('/Notification/read', [controller\System\NotificationController::class, 'read']);
    Route::post('/Notification/del', [controller\System\NotificationController::class, 'del']);

    // 聊天（APP/H5）—— 复用 admin 端 ChatController
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
})->middleware([
    app\middleware\CheckToken::class,
    app\middleware\CheckPermission::class,
    app\middleware\OperationLog::class,
]);

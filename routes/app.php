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
    Route::post('/Notification/list', [controller\System\NotificationController::class, 'list']);
    Route::post('/Notification/unreadCount', [controller\System\NotificationController::class, 'unreadCount']);
    Route::post('/Notification/read', [controller\System\NotificationController::class, 'read']);
    Route::post('/Notification/del', [controller\System\NotificationController::class, 'del']);
})->middleware([
    app\middleware\CheckToken::class,
    app\middleware\CheckPermission::class,
    app\middleware\OperationLog::class,
]);

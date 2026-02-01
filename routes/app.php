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
})->middleware([
    app\middleware\CheckToken::class,
    app\middleware\CheckPermission::class,
    app\middleware\OperationLog::class,
]);

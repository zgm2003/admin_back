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

});

Route::group('/api/admin', function () {
    // 需要认证的接口

    //菜单管理
    Route::post('/Permission/init', [controller\User\PermissionController::class, 'init']);
    Route::post('/Permission/add', [controller\User\PermissionController::class, 'add']);
    Route::post('/Permission/edit', [controller\User\PermissionController::class, 'edit']);
    Route::post('/Permission/del', [controller\User\PermissionController::class, 'del']);
    Route::post('/Permission/list', [controller\User\PermissionController::class, 'list']);
    Route::post('/Permission/batchEdit', [controller\User\PermissionController::class, 'batchEdit']);
    Route::post('/Permission/status', [controller\User\PermissionController::class, 'status']);

    //角色管理
    Route::post('/Role/init', [controller\User\RoleController::class, 'init']);
    Route::post('/Role/list', [controller\User\RoleController::class, 'list']);
    Route::post('/Role/add', [controller\User\RoleController::class, 'add']);
    Route::post('/Role/edit', [controller\User\RoleController::class, 'edit']);
    Route::post('/Role/del', [controller\User\RoleController::class, 'del']);
    Route::post('/Role/default', [controller\User\RoleController::class, 'default']);

    //用户管理
    Route::post('/Users/initList', [controller\User\UsersController::class, 'initList']);
    Route::post('/Users/editList', [controller\User\UsersController::class, 'editList']);
    Route::post('/Users/delList', [controller\User\UsersController::class, 'delList']);
    Route::post('/Users/listList', [controller\User\UsersController::class, 'listList']);
    Route::post('/Users/batchEditList', [controller\User\UsersController::class, 'batchEditList']);
    Route::post('/Users/exportList', [controller\User\UsersController::class, 'exportList']);

    //操作日志管理
    Route::post('/OperationLog/init', [controller\System\OperationLogController::class, 'init']);
    Route::post('/OperationLog/list', [controller\System\OperationLogController::class, 'list']);
    Route::post('/OperationLog/del', [controller\System\OperationLogController::class, 'del']);

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
})->middleware([
    app\middleware\CheckToken::class,
    app\middleware\OperationLog::class
]);

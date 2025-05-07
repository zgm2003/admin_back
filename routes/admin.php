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

Route::group('/api/admin', function () {
    //不需要认证的接口

});

Route::group('/api/admin', function () {
    // 需要认证的接口

    //菜单管理
    Route::add(['POST', 'OPTIONS'], '/Permission/init', [controller\User\PermissionController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/Permission/add', [controller\User\PermissionController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/Permission/edit', [controller\User\PermissionController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/Permission/del', [controller\User\PermissionController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/Permission/list', [controller\User\PermissionController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Permission/batchEdit', [controller\User\PermissionController::class, 'batchEdit']);
    Route::add(['POST', 'OPTIONS'], '/Permission/status', [controller\User\PermissionController::class, 'status']);

    //角色管理
    Route::add(['POST', 'OPTIONS'], '/Role/init', [controller\User\RoleController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/Role/list', [controller\User\RoleController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Role/add', [controller\User\RoleController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/Role/edit', [controller\User\RoleController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/Role/del', [controller\User\RoleController::class, 'del']);

    //用户管理
    Route::add(['POST', 'OPTIONS'], '/Users/initList', [controller\User\UsersController::class, 'initList']);
    Route::add(['POST', 'OPTIONS'], '/Users/editList', [controller\User\UsersController::class, 'editList']);
    Route::add(['POST', 'OPTIONS'], '/Users/delList', [controller\User\UsersController::class, 'delList']);
    Route::add(['POST', 'OPTIONS'], '/Users/listList', [controller\User\UsersController::class, 'listList']);
    Route::add(['POST', 'OPTIONS'], '/Users/batchEditList', [controller\User\UsersController::class, 'batchEditList']);

})->middleware([
    app\middleware\CheckToken::class,
]);
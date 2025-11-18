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

// 兜底预检：拦截 /api 下所有 OPTIONS，避免 404/无头导致预检失败
Route::group('/api', function () {
    Route::add(['OPTIONS'], '/{path:.+}', function () {
        return response('');
    });
});

Route::group('/api', function () {
    Route::post('/Users/register', [controller\User\UsersController::class, 'register']);
    Route::post('/Users/login', [controller\User\UsersController::class, 'login']);
    Route::post('/Users/sendCode', [controller\User\UsersController::class, 'sendCode']);
    Route::post('/Users/forgetPassword', [controller\User\UsersController::class, 'forgetPassword']);

    Route::post('/test', [controller\TestController::class, 'test']);
    Route::post('/test/sendTest', [controller\TestController::class, 'sendTest']);
});

Route::group('/api', function () {
    // 需要认证的接口
    Route::post('/Users/init', [controller\User\UsersController::class, 'init']);
    Route::post('/Users/initPersonal', [controller\User\UsersController::class, 'initPersonal']);
    Route::post('/Users/editPersonal', [controller\User\UsersController::class, 'editPersonal']);
    Route::post('/Users/EditPassword', [controller\User\UsersController::class, 'EditPassword']);


    Route::post('/getUploadToken', [controller\CosUploadController::class, 'getUploadToken']);


})->middleware([
    app\middleware\CheckToken::class,
]);
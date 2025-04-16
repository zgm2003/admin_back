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

Route::group('/api', function () {
    Route::add(['POST', 'OPTIONS'],'/Users/register', [controller\User\UsersController::class, 'register']);
    Route::add(['POST', 'OPTIONS'],'/Users/login', [controller\User\UsersController::class, 'login']);
    Route::add(['POST', 'OPTIONS'],'/Users/sendCode', [controller\User\UsersController::class, 'sendCode']);
    Route::add(['POST', 'OPTIONS'],'/Users/forgetPassword', [controller\User\UsersController::class, 'forgetPassword']);

    Route::add(['POST', 'OPTIONS'],'/test', [controller\TestController::class, 'test']);
});

Route::group('/api', function () {
    // 需要认证的接口
    Route::add(['POST', 'OPTIONS'],'/Users/init', [controller\User\UsersController::class, 'init']);
    Route::add(['POST', 'OPTIONS'],'/Users/initPersonal', [controller\User\UsersController::class, 'initPersonal']);
    Route::add(['POST', 'OPTIONS'],'/Users/editPersonal', [controller\User\UsersController::class, 'editPersonal']);
    Route::add(['POST', 'OPTIONS'],'/Users/EditPassword', [controller\User\UsersController::class, 'EditPassword']);


    Route::add(['POST', 'OPTIONS'],'/getUploadToken', [controller\CosUploadController::class, 'getUploadToken']);

})->middleware([
    app\middleware\CheckToken::class,
]);
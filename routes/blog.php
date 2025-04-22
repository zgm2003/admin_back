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

Route::group('/api/blog', function () {
    //博客
    Route::add(['POST', 'OPTIONS'],'/init', [controller\Blog\BlogController::class, 'init']);
    Route::add(['POST', 'OPTIONS'],'/list', [controller\Blog\BlogController::class, 'list']);
    Route::add(['POST', 'OPTIONS'],'/detail', [controller\Blog\BlogController::class, 'detail']);

    //用户信息
    Route::add(['POST', 'OPTIONS'],'/Users/userInfo', [controller\User\UsersController::class, 'userInfo']);

    //文章收藏
    Route::add(['POST', 'OPTIONS'],'/Star/starCount', [controller\Blog\StarController::class, 'starCount']);

    //评论列表
    Route::add(['POST', 'OPTIONS'],'/Comment/list', [controller\Web\CommentController::class, 'list']);

    //音乐
    Route::add(['POST', 'OPTIONS'],'/Music/init', [controller\Web\MusicController::class, 'init']);

    //留言管理
    Route::add(['POST', 'OPTIONS'],'/Message/add', [controller\Web\MessageController::class, 'add']);
    Route::add(['POST', 'OPTIONS'],'/Message/list', [controller\Web\MessageController::class, 'list']);

    //热搜
    Route::add(['POST', 'OPTIONS'],'/HotSearch', [controller\Blog\HotSearchController::class, 'hostSearch']);

    //相册
    Route::add(['POST', 'OPTIONS'],'/Album/init', [controller\Web\AlbumController::class, 'init']);
    Route::add(['POST', 'OPTIONS'],'/Album/detail', [controller\Web\AlbumController::class, 'detail']);
    Route::add(['POST', 'OPTIONS'],'/Album/check', [controller\Web\AlbumController::class, 'check']);

    //AI工具
    Route::add(['POST', 'OPTIONS'],'/Ai/init', [controller\Ai\AiController::class, 'init1']);
    Route::add(['POST', 'OPTIONS'],'/Ai/list', [controller\Ai\AiController::class, 'list1']);
    Route::add(['POST', 'OPTIONS'],'/Ai/homeModule', [controller\Ai\AiController::class, 'homeModule']);
    Route::add(['POST', 'OPTIONS'],'/Ai/categoryList', [controller\Ai\AiController::class, 'categoryList']);
});

Route::group('/api/blog', function () {
    // 需要认证的接口

    //文章收藏
    Route::add(['POST', 'OPTIONS'],'/Star/add', [controller\Blog\StarController::class, 'add']);
    Route::add(['POST', 'OPTIONS'],'/Star/del', [controller\Blog\StarController::class, 'del']);
    Route::add(['POST', 'OPTIONS'],'/Star/isStar', [controller\Blog\StarController::class, 'isStar']);

    //博客评论
    Route::add(['POST', 'OPTIONS'],'/Comment/add', [controller\Web\CommentController::class, 'add']);


})->middleware([
    app\middleware\CheckToken::class,
]);
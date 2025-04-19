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

    //拼多多
    Route::add(['POST', 'OPTIONS'], '/platform/pinduoduo/loginKey', [controller\AiWorkLine\E_commerce\platform\PinduoduoController::class, 'loginKey']);
    Route::add(['GET', 'OPTIONS'], '/platform/pinduoduo/callback', [controller\AiWorkLine\E_commerce\platform\PinduoduoController::class, 'callback']);

    //阿里云Token
    Route::add(['POST', 'OPTIONS'], '/platform/AliCloud/getToken', [controller\AiWorkLine\E_commerce\platform\AliCloudController::class, 'getToken']);

    //AI工作流-电商-精选商品库
    Route::add(['POST', 'OPTIONS'], '/Goods/add', [controller\AiWorkLine\E_commerce\GoodsController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/Goods/getImage', [controller\AiWorkLine\E_commerce\GoodsController::class, 'getImage']);
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

    //文章分类
    Route::add(['POST', 'OPTIONS'], '/ArticleCategory/init', [controller\Article\CategoryController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/ArticleCategory/list', [controller\Article\CategoryController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/ArticleCategory/add', [controller\Article\CategoryController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/ArticleCategory/edit', [controller\Article\CategoryController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/ArticleCategory/del', [controller\Article\CategoryController::class, 'del']);

    //文章标签
    Route::add(['POST', 'OPTIONS'], '/ArticleTag/init', [controller\Article\TagController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/ArticleTag/list', [controller\Article\TagController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/ArticleTag/add', [controller\Article\TagController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/ArticleTag/edit', [controller\Article\TagController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/ArticleTag/del', [controller\Article\TagController::class, 'del']);

    //文章
    Route::add(['POST', 'OPTIONS'], '/Article/init', [controller\Article\ArticleController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/Article/list', [controller\Article\ArticleController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Article/add', [controller\Article\ArticleController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/Article/edit', [controller\Article\ArticleController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/Article/del', [controller\Article\ArticleController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/Article/batchEdit', [controller\Article\ArticleController::class, 'batchEdit']);
    Route::add(['POST', 'OPTIONS'], '/Article/testPrompt', [controller\Article\ArticleController::class, 'testPrompt']);
    Route::add(['POST', 'OPTIONS'], '/Article/confirmPrompt', [controller\Article\ArticleController::class, 'confirmPrompt']);
    Route::add(['POST', 'OPTIONS'], '/Article/toModel', [controller\Article\ArticleController::class, 'toModel']);
    Route::add(['POST', 'OPTIONS'], '/Article/toReview', [controller\Article\ArticleController::class, 'toReview']);
    Route::add(['POST', 'OPTIONS'], '/Article/toRelease', [controller\Article\ArticleController::class, 'toRelease']);
    Route::add(['POST', 'OPTIONS'], '/Article/toRemove', [controller\Article\ArticleController::class, 'toRemove']);

    //留言管理
    Route::add(['POST', 'OPTIONS'], '/Message/list', [controller\Web\MessageController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Message/del', [controller\Web\MessageController::class, 'del']);

    //评论管理
    Route::add(['POST', 'OPTIONS'], '/Comment/list', [controller\Web\CommentController::class, 'listList']);
    Route::add(['POST', 'OPTIONS'], '/Comment/del', [controller\Web\CommentController::class, 'del']);

    //相册管理
    Route::add(['POST', 'OPTIONS'], '/Album/list', [controller\Web\AlbumController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Album/add', [controller\Web\AlbumController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/Album/edit', [controller\Web\AlbumController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/Album/del', [controller\Web\AlbumController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/Album/batchEdit', [controller\Web\AlbumController::class, 'batchEdit']);

    //访客管理
    Route::add(['POST', 'OPTIONS'], '/Visitor/list', [controller\Web\VisitorController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Visitor/del', [controller\Web\VisitorController::class, 'del']);

    //音乐管理
    Route::add(['POST', 'OPTIONS'], '/Music/add', [controller\Web\MusicController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/Music/list', [controller\Web\MusicController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Music/edit', [controller\Web\MusicController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/Music/del', [controller\Web\MusicController::class, 'del']);

    //AI管理-分类
    Route::add(['POST', 'OPTIONS'], '/AiCategory/add', [controller\Ai\CategoryController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/AiCategory/list', [controller\Ai\CategoryController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/AiCategory/del', [controller\Ai\CategoryController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/AiCategory/edit', [controller\Ai\CategoryController::class, 'edit']);

    //AI管理
    Route::add(['POST', 'OPTIONS'], '/Ai/init', [controller\Ai\AiController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/Ai/add', [controller\Ai\AiController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/Ai/list', [controller\Ai\AiController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Ai/edit', [controller\Ai\AiController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/Ai/del', [controller\Ai\AiController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/Ai/batchEdit', [controller\Ai\AiController::class, 'batchEdit']);

    //语音合成-音色列表
    Route::add(['POST', 'OPTIONS'], '/Voices/init', [controller\VoicesController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/Voices/list', [controller\VoicesController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Voices/del', [controller\VoicesController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/Voices/listen', [controller\VoicesController::class, 'listen']);

    //AI工作流-电商-账号管理
    Route::add(['POST', 'OPTIONS'], '/Account/init', [controller\AiWorkLine\E_commerce\AccountController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/Account/list', [controller\AiWorkLine\E_commerce\AccountController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Account/del', [controller\AiWorkLine\E_commerce\AccountController::class, 'del']);

    //AI工作流-电商-选品-拼多多选品
    Route::add(['POST', 'OPTIONS'], '/PinDuoDuoChangeGoods/init', [controller\AiWorkLine\E_commerce\PinDuoDuoChangeGoodsController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/PinDuoDuoChangeGoods/list', [controller\AiWorkLine\E_commerce\PinDuoDuoChangeGoodsController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/PinDuoDuoChangeGoods/export', [controller\AiWorkLine\E_commerce\PinDuoDuoChangeGoodsController::class, 'export']);

    //AI工作流-电商-精选商品库
    Route::add(['POST', 'OPTIONS'], '/Goods/init', [controller\AiWorkLine\E_commerce\GoodsController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/Goods/edit', [controller\AiWorkLine\E_commerce\GoodsController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/Goods/batchEdit', [controller\AiWorkLine\E_commerce\GoodsController::class, 'batchEdit']);
    Route::add(['POST', 'OPTIONS'], '/Goods/list', [controller\AiWorkLine\E_commerce\GoodsController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/Goods/del', [controller\AiWorkLine\E_commerce\GoodsController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/Goods/testPrompt', [controller\AiWorkLine\E_commerce\GoodsController::class, 'testPrompt']);
    Route::add(['POST', 'OPTIONS'], '/Goods/confirmPrompt', [controller\AiWorkLine\E_commerce\GoodsController::class, 'confirmPrompt']);
    Route::add(['POST', 'OPTIONS'], '/Goods/toOcr', [controller\AiWorkLine\E_commerce\GoodsController::class, 'toOcr']);
    Route::add(['POST', 'OPTIONS'], '/Goods/toModel', [controller\AiWorkLine\E_commerce\GoodsController::class, 'toModel']);
    Route::add(['POST', 'OPTIONS'], '/Goods/toSpeech', [controller\AiWorkLine\E_commerce\GoodsController::class, 'toSpeech']);

    //AI工作流-AI生图/视频-提示词
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoPrompt/init', [controller\AiWorkLine\AiImageVideo\AiImageVideoPromptController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoPrompt/add', [controller\AiWorkLine\AiImageVideo\AiImageVideoPromptController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoPrompt/testPrompt', [controller\AiWorkLine\AiImageVideo\AiImageVideoPromptController::class, 'testPrompt']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoPrompt/list', [controller\AiWorkLine\AiImageVideo\AiImageVideoPromptController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoPrompt/edit', [controller\AiWorkLine\AiImageVideo\AiImageVideoPromptController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoPrompt/del', [controller\AiWorkLine\AiImageVideo\AiImageVideoPromptController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoPrompt/changePrmopt', [controller\AiWorkLine\AiImageVideo\AiImageVideoPromptController::class, 'changePrmopt']);

    //AI工作流-AI生图/视频-任务
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoTask/init', [controller\AiWorkLine\AiImageVideo\AiImageVideoTaskController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoTask/testPrompt', [controller\AiWorkLine\AiImageVideo\AiImageVideoTaskController::class, 'testPrompt']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoTask/add', [controller\AiWorkLine\AiImageVideo\AiImageVideoTaskController::class, 'add']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoTask/list', [controller\AiWorkLine\AiImageVideo\AiImageVideoTaskController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoTask/del', [controller\AiWorkLine\AiImageVideo\AiImageVideoTaskController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoTask/edit', [controller\AiWorkLine\AiImageVideo\AiImageVideoTaskController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideoTask/toPrompt', [controller\AiWorkLine\AiImageVideo\AiImageVideoTaskController::class, 'toPrompt']);

    //AI工作流-AI生图/视频-主线程
    Route::add(['POST', 'OPTIONS'], '/AiImageVideo/init', [controller\AiWorkLine\AiImageVideo\AiImageVideoController::class, 'init']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideo/list', [controller\AiWorkLine\AiImageVideo\AiImageVideoController::class, 'list']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideo/del', [controller\AiWorkLine\AiImageVideo\AiImageVideoController::class, 'del']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideo/edit', [controller\AiWorkLine\AiImageVideo\AiImageVideoController::class, 'edit']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideo/batchEdit', [controller\AiWorkLine\AiImageVideo\AiImageVideoController::class, 'batchEdit']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideo/toImage', [controller\AiWorkLine\AiImageVideo\AiImageVideoController::class, 'toImage']);
    Route::add(['POST', 'OPTIONS'], '/AiImageVideo/toVideo', [controller\AiWorkLine\AiImageVideo\AiImageVideoController::class, 'toVideo']);

})->middleware([
    app\middleware\CheckToken::class,
]);
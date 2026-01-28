<?php
// 文件：app/middleware/CheckPermission.php

namespace app\middleware;

use app\enum\ErrorCodeEnum;
use support\Cache;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\service\AnnotationHelper;
use app\dep\User\UsersDep;
use app\service\User\PermissionService;

class CheckPermission implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $permissionCode = AnnotationHelper::getPermissionAnnotation($request);
        if (!$permissionCode) {
            return $handler($request);
        }

        // 已经由 CheckToken 保证 userId 和 platform 存在
        $platform = $request->platform;
        $cacheKey = 'auth_perm_uid_' . $request->userId . '_' . $platform;
        $buttonCodes = Cache::get($cacheKey);

        if (!is_array($buttonCodes)) {
            // 尝试重新加载权限，而不是直接返回错误
            try {
                // 实例化依赖 (手动实例化以确保在中间件中可用)
                $usersDep = new UsersDep();
                $permissionService = new PermissionService();

                $user = $usersDep->find($request->userId);
                
                if ($user) {
                    // 重新计算权限（按平台过滤）
                    $perm = $permissionService->buildPermissionContextByUser($user, $platform);
                    $buttonCodes = $perm['buttonCodes'];
                    
                    // 重新写入缓存
                    Cache::set($cacheKey, $buttonCodes, 300);
                }
            } catch (\Exception $e) {
                // 记录日志或忽略，走下面的错误返回逻辑
            }
        }

        if (!is_array($buttonCodes)) {
            return json([
                'code' => ErrorCodeEnum::FORBIDDEN,
                'msg'  => '权限缓存失效，请重新登录',
                'data' => [],
            ]);
        }

        if (!in_array($permissionCode, $buttonCodes, true)) {
            return json([
                'code' => ErrorCodeEnum::FORBIDDEN,
                'msg'  => '无权限访问',
                'data' => [],
            ]);
        }

        return $handler($request);
    }
}

<?php
// 文件：app/middleware/CheckPermission.php

namespace app\middleware;

use app\dep\User\UsersDep;
use app\enum\CacheTTLEnum;
use app\enum\ErrorCodeEnum;
use app\service\Common\AnnotationHelper;
use app\service\User\PermissionService;
use support\Cache;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

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
                $usersDep = new UsersDep();

                $user = $usersDep->find($request->userId);
                
                if ($user) {
                    // 重新计算权限（按平台过滤）
                    $perm = PermissionService::buildPermissionContextByUser($user, $platform);
                    $buttonCodes = $perm['buttonCodes'];
                    
                    // 重新写入缓存
                    Cache::set($cacheKey, $buttonCodes, CacheTTLEnum::PERMISSION_BUTTONS);
                }
            } catch (\Exception $e) {
                log_daily('permission')->error('权限加载失败: ' . $e->getMessage(), [
                    'user_id'  => $request->userId,
                    'platform' => $platform,
                ]);
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

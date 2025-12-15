<?php
// 文件：app/middleware/OperationLogMiddleware.php

namespace app\middleware;

use app\enum\ErrorCodeEnum;
use support\Cache;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\service\AnnotationHelper;
class CheckPermission implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $permissionCode = AnnotationHelper::getPermissionAnnotation($request);
        if (!$permissionCode) {
            return $handler($request);
        }

        // 已经由 CheckToken 保证 userId 存在
        $cacheKey = 'auth_perm_uid_' . $request->userId;
        $buttonCodes = Cache::get($cacheKey);

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

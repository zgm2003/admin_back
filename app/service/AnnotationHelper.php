<?php

namespace app\service;

use Webman\Http\Request;

/**
 * 注解解析助手
 * 通过反射读取控制器方法的 DocBlock 注解
 * 支持：@OperationLog("描述") / @Permission("权限码")
 */
class AnnotationHelper
{
    /**
     * 提取 @OperationLog("描述") 注解内容
     */
    public static function getOperationLogAnnotation(Request $request): ?string
    {
        return self::extractAnnotation($request, 'OperationLog');
    }

    /**
     * 提取 @Permission("权限码") 注解内容
     */
    public static function getPermissionAnnotation(Request $request): ?string
    {
        return self::extractAnnotation($request, 'Permission');
    }

    // ==================== 私有方法 ====================

    /**
     * 通用注解提取：反射控制器方法 DocBlock，匹配 @{tag}("value")
     */
    private static function extractAnnotation(Request $request, string $tag): ?string
    {
        $controllerClass = $request->controller;
        $method          = $request->action;

        if (empty($controllerClass) || empty($method)) {
            return null;
        }

        try {
            $refMethod  = new \ReflectionMethod($controllerClass, $method);
            $docComment = $refMethod->getDocComment();

            if ($docComment && preg_match("/@{$tag}\(\"(.+?)\"\)/", $docComment, $matches)) {
                return $matches[1];
            }
        } catch (\ReflectionException) {
            // 类或方法不存在，静默返回 null
        }

        return null;
    }
}
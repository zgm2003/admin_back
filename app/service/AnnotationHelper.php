<?php

namespace app\service;

use Webman\Http\Request;
use ReflectionMethod;

class AnnotationHelper
{
    /**
     * 从当前 Webman 请求中反射出控制器方法，提取 @OperationLog("描述") 注解内容
     *
     * @param Request $request
     * @return string|null  注解中的描述，没找到返回 null
     */
    public static function getOperationLogAnnotation(Request $request): ?string
    {
        // Webman 自动将 controller 类名和方法名注入到请求对象
        $controllerClass = $request->controller; // 如 app\controller\YourController
        $method          = $request->action;     // 如 "add"

        if (empty($controllerClass) || empty($method)) {
            return null;
        }

        try {
            $refMethod  = new ReflectionMethod($controllerClass, $method);
            $docComment = $refMethod->getDocComment();
            if (! $docComment) {
                return null;
            }

            // 匹配 @OperationLog("描述内容")
            if (preg_match('/@OperationLog\("(.+?)"\)/', $docComment, $matches)) {
                return $matches[1];
            }
        } catch (\ReflectionException $e) {
            // 类或方法不存在
            return null;
        }

        return null;
    }

    public static function getPermissionAnnotation(Request $request): ?string
    {
        $controllerClass = $request->controller;
        $method          = $request->action;

        if (empty($controllerClass) || empty($method)) {
            return null;
        }

        try {
            $refMethod  = new \ReflectionMethod($controllerClass, $method);
            $docComment = $refMethod->getDocComment();
            if (! $docComment) {
                return null;
            }

            // 匹配 @Permission("xxx.xxx")
            if (preg_match('/@Permission\("(.+?)"\)/', $docComment, $matches)) {
                return $matches[1];
            }
        } catch (\ReflectionException $e) {
            return null;
        }

        return null;
    }

}

<?php

namespace app\exception;

use Webman\Exception\ExceptionHandler;
use Webman\Http\Request;
use Webman\Http\Response;
use Throwable;

/**
 * 全局异常处理器
 * 类似 Java 的 @ControllerAdvice + @ExceptionHandler
 */
class Handler extends ExceptionHandler
{
    /**
     * 不需要记录日志的异常（业务异常属于正常流程）
     */
    public $dontReport = [
        BusinessException::class,
    ];

    /**
     * 统一异常响应
     */
    public function render(Request $request, Throwable $e): Response
    {
        // 业务异常：直接返回错误信息
        if ($e instanceof BusinessException) {
            return json([
                'code' => $e->getCode(),
                'data' => [],
                'msg'  => $e->getMessage(),
            ]);
        }

        // 未知异常：生产环境隐藏细节
        $msg = config('app.debug') ? $e->getMessage() : '服务器错误';
        
        return json([
            'code' => 500,
            'data' => [],
            'msg'  => $msg,
        ]);
    }
}

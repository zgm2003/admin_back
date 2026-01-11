<?php

namespace support\exception;

use app\enum\ErrorCodeEnum;
use Psr\Log\LoggerInterface;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Exception\ExceptionHandlerInterface;

/**
 * 统一异常处理器
 * 确保所有异常响应格式与业务响应一致：{ code, data, msg }
 */
class Handler implements ExceptionHandlerInterface
{
    protected LoggerInterface $logger;
    protected bool $debug;

    /**
     * 不需要记录日志的异常类型
     */
    public array $dontReport = [
        \app\exception\BusinessException::class,
    ];

    public function __construct($logger, $debug)
    {
        $this->logger = $logger;
        $this->debug = $debug;
    }

    /**
     * 记录异常日志
     */
    public function report(Throwable $exception): void
    {
        if ($this->shouldntReport($exception)) {
            return;
        }

        $logs = '';
        if ($request = \request()) {
            $logs = $request->getRealIp() . ' ' . $request->method() . ' ' . trim($request->fullUrl(), '/');
        }
        $this->logger->error($logs . PHP_EOL . $exception);
    }

    /**
     * 渲染异常响应
     */
    public function render(Request $request, Throwable $exception): Response
    {
        // 如果异常自带 render 方法，优先使用
        if (method_exists($exception, 'render')) {
            // @phpstan-ignore-next-line 动态方法调用
            $response = $exception->render($request);
            if ($response instanceof Response) {
                return $response;
            }
        }

        // 确定错误码
        $code = $this->resolveCode($exception);
        
        // 确定错误消息
        $msg = $this->resolveMessage($exception);

        // 构建响应数据
        $data = [];
        if ($this->debug) {
            $data['exception'] = get_class($exception);
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
            $data['trace'] = $exception->getTraceAsString();
        }

        // 统一 JSON 响应格式
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'code' => $code,
                'data' => $data,
                'msg'  => $msg,
            ], JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * 解析错误码
     */
    protected function resolveCode(Throwable $exception): int
    {
        $code = $exception->getCode();

        // BusinessException 直接使用其 code
        if ($exception instanceof \app\exception\BusinessException) {
            return $code ?: ErrorCodeEnum::PARAM_ERROR;
        }

        // 验证异常
        if ($exception instanceof \Respect\Validation\Exceptions\ValidationException) {
            return ErrorCodeEnum::PARAM_ERROR;
        }

        // 其他异常默认 500
        return $code ?: ErrorCodeEnum::SERVER_ERROR;
    }

    /**
     * 解析错误消息
     */
    protected function resolveMessage(Throwable $exception): string
    {
        // BusinessException 和验证异常直接显示消息
        if ($exception instanceof \app\exception\BusinessException ||
            $exception instanceof \Respect\Validation\Exceptions\ValidationException) {
            return $exception->getMessage();
        }

        // 其他异常：debug 模式显示详情，否则显示通用消息
        return $this->debug ? $exception->getMessage() : 'Server internal error';
    }

    /**
     * 判断是否需要记录日志
     */
    protected function shouldntReport(Throwable $e): bool
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }
        return false;
    }
}

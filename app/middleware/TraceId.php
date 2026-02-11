<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * TraceId 链路追踪中间件
 * 从前端接收 X-Trace-Id，若无则自动生成
 * 存入请求上下文，供日志记录使用
 */
class TraceId implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 获取或生成 trace_id
        $traceId = $request->header('X-Trace-Id') ?: $this->generateTraceId();
        
        // 存入请求属性，供后续使用
        $request->traceId = $traceId;
        
        // 记录请求开始时间
        $startTime = microtime(true);
        $request->startTime = $startTime;
        
        // 执行请求
        $response = $handler($request);
        
        // 计算耗时
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        // 在响应头中返回 trace_id 和耗时，便于前端调试
        $response->withHeader('X-Trace-Id', $traceId);
        $response->withHeader('X-Response-Time', $duration . 'ms');
        
        return $response;
    }
    
    /**
     * 生成唯一的 trace_id
     */
    private function generateTraceId(): string
    {
        return sprintf('%s-%s', 
            base_convert((string)intval(microtime(true) * 1000), 10, 36),
            bin2hex(random_bytes(4))
        );
    }
}

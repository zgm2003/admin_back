<?php
// 文件：app/middleware/OperationLog.php

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use Webman\RedisQueue\Redis;
use Throwable;
use app\enum\CommonEnum;
use app\service\AnnotationHelper;

class OperationLog implements MiddlewareInterface
{
    /**
     * @param Request   $request
     * @param callable  $handler
     * @return Response
     * @throws Throwable
     */
    public function process(Request $request, callable $handler): Response
    {
        // 1. 解析注解
        $action = AnnotationHelper::getOperationLogAnnotation($request);
        if (! $action) {
            return $handler($request);
        }

        // 2. 准备基础信息
        $userId = $request->userId ?? 0;
        $rawReq = $request->all();

        try {
            // 3. 执行业务
            $response = $handler($request);

            // 4. 解析响应
            $content    = $response->rawBody();
            $parsed     = json_decode($content, true);
            $bizSuccess = isset($parsed['code'])
                ? ($parsed['code'] === 0)
                : ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300);

            // 5. 构造队列数据
            $data = [
                'user_id'       => $userId,
                'action'        => $action,
                'request_data'  => json_encode($rawReq, JSON_UNESCAPED_UNICODE),
                'response_data' => json_encode($parsed ?? ['raw' => $content], JSON_UNESCAPED_UNICODE),
                'is_success'    => $bizSuccess ? CommonEnum::YES : CommonEnum::NO,
            ];

        } catch (Throwable $e) {
            // 6. 异常场景也要构造数据并入队
            $data = [
                'user_id'       => $userId,
                'action'        => $action,
                'request_data'  => json_encode($rawReq, JSON_UNESCAPED_UNICODE),
                'response_data' => json_encode([
                    'error' => $e->getMessage(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : 'hidden',
                ], JSON_UNESCAPED_UNICODE),
                'is_success'    => CommonEnum::NO,
            ];

            // 异常时也推送到队列
            Redis::send('operation_log', $data);
            throw $e;
        }

        // 7. 正常场景推送到队列
        Redis::send('operation_log', $data);

        return $response;
    }
}

<?php

namespace app\module;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;
use support\Request;

class BaseModule
{
    // 业务通用状态码常量（与 HTTP 状态码区分）
    const CODE_SUCCESS       = 0;     // 成功
    const CODE_PARAM_ERROR   = 100;   // 参数/校验错误
    const CODE_UNAUTHORIZED  = 401;   // 未认证（业务层语义）
    const CODE_FORBIDDEN     = 403;   // 无权限（业务层语义）
    const CODE_NOT_FOUND     = 404;   // 资源不存在（业务层语义）
    const CODE_SERVER_ERROR  = 500;   // 服务器内部错误（业务层语义）

    /**
     * 统一响应底层方法（保持兼容：返回 [data, code, msg]）
     */
    public static function response(array $data = [], string $msg = 'success', int $code = self::CODE_SUCCESS): array
    {
        return [$data, $code, $msg];
    }

    /**
     * 成功响应（code 固定为 0）
     */
    public static function success(array $data = [], string $msg = 'success'): array
    {
        return [
            $data,
            self::CODE_SUCCESS,
            $msg,
        ];
    }

    /**
     * 失败/错误响应（默认使用业务错误码 100，可自定义）
     */
    public static function error(string $msg = 'error', int $code = self::CODE_PARAM_ERROR, array $data = []): array
    {
        return [
            $data,
            $code,
            $msg,
        ];
    }

    /**
     * 别名：fail（等价于 error）
     */
    public static function fail(string $msg = 'error', int $code = self::CODE_PARAM_ERROR, array $data = []): array
    {
        return self::error($msg, $code, $data);
    }

    /**
     * 分页响应快捷方法
     * 传入列表与分页信息，结构统一为 { list, page }
     */
    public static function paginate($list, array $page, string $msg = 'success'): array
    {
        return [
            [
                'list' => $list,
                'page' => $page,
            ],
            self::CODE_SUCCESS,
            $msg,
        ];
    }

    /**
     * 异常转统一响应（在 debug 模式下可附带 trace）
     */
    public static function fromException(\Throwable $e, int $code = self::CODE_SERVER_ERROR, array $data = []): array
    {
        if (function_exists('config') && (bool)config('app.debug')) {
            $data['trace'] = $e->getTraceAsString();
        }
        return [$data, $code, $e->getMessage() ?: 'server error'];
    }

    public static function validate(Request $request, array $rules, array $input = null): array
    {
        try {
            $data = v::input($input ?? $request->all(), $rules);
            return [$data, self::CODE_SUCCESS, 'success'];
        } catch (ValidationException $e) {
            return [[], self::CODE_PARAM_ERROR, $e->getMessage()];
        }
    }
}

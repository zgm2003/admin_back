<?php

namespace app\module;

use app\enum\ErrorCodeEnum;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;
use support\Db;
use support\Request;

class BaseModule
{
    // 业务通用状态码常量（与 HTTP 状态码区分）
    const CODE_SUCCESS       = ErrorCodeEnum::SUCCESS;      // 成功
    const CODE_PARAM_ERROR   = ErrorCodeEnum::PARAM_ERROR;  // 参数/校验错误
    const CODE_UNAUTHORIZED  = ErrorCodeEnum::UNAUTHORIZED; // 未认证（业务层语义）
    const CODE_FORBIDDEN     = ErrorCodeEnum::FORBIDDEN;    // 无权限（业务层语义）
    const CODE_NOT_FOUND     = ErrorCodeEnum::NOT_FOUND;    // 资源不存在（业务层语义）
    const CODE_SERVER_ERROR  = ErrorCodeEnum::SERVER_ERROR; // 服务器内部错误（业务层语义）

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

    /**
     * 事务封装
     * @param callable $callback 事务内执行的回调
     * @return mixed 回调返回值
     * @throws \Throwable
     */
    protected function withTransaction(callable $callback)
    {
        Db::beginTransaction();
        try {
            $result = $callback();
            Db::commit();
            return $result;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    protected function validate(Request $request, array $rules, ?array $input = null): array
    {
        try {
            return v::input($input ?? $request->all(), $rules);
        } catch (ValidationException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }

}

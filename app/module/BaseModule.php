<?php

namespace app\module;

use app\enum\ErrorCodeEnum;
use app\exception\BusinessException;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;
use support\Db;
use support\Request;

class BaseModule
{
    /** @var array<class-string, object> */
    private array $instances = [];

    // ==================== 状态码常量 ====================
    const CODE_SUCCESS       = ErrorCodeEnum::SUCCESS;
    const CODE_PARAM_ERROR   = ErrorCodeEnum::PARAM_ERROR;
    const CODE_UNAUTHORIZED  = ErrorCodeEnum::UNAUTHORIZED;
    const CODE_FORBIDDEN     = ErrorCodeEnum::FORBIDDEN;
    const CODE_NOT_FOUND     = ErrorCodeEnum::NOT_FOUND;
    const CODE_SERVER_ERROR  = ErrorCodeEnum::SERVER_ERROR;

    // ==================== 响应方法 ====================

    /**
     * 成功响应
     */
    public static function success(array $data = [], string $msg = 'success'): array
    {
        return [$data, self::CODE_SUCCESS, $msg];
    }

    /**
     * 失败响应
     */
    public static function error(string $msg = 'error', int $code = self::CODE_PARAM_ERROR, array $data = []): array
    {
        return [$data, $code, $msg];
    }

    /**
     * 分页响应
     */
    public static function paginate($list, array $page, string $msg = 'success'): array
    {
        return [['list' => $list, 'page' => $page], self::CODE_SUCCESS, $msg];
    }

    /**
     * 游标分页响应
     */
    public static function cursorPaginate($list, ?int $nextCursor, bool $hasMore, string $msg = 'success'): array
    {
        return [['list' => $list, 'next_cursor' => $nextCursor, 'has_more' => $hasMore], self::CODE_SUCCESS, $msg];
    }

    // ==================== 异常快捷方法 ====================

    /**
     * 直接抛出业务异常
     * @throws BusinessException
     */
    public static function throw(string $msg, int $code = self::CODE_PARAM_ERROR): void
    {
        throw new BusinessException($msg, $code);
    }

    /**
     * 条件为真值时抛出异常（支持 PHP 隐式类型转换）
     * @throws BusinessException
     */
    public static function throwIf(mixed $condition, string $msg, int $code = self::CODE_PARAM_ERROR): void
    {
        if ($condition) {
            throw new BusinessException($msg, $code);
        }
    }

    /**
     * 条件为 false/null/empty 时抛出异常（常用于检查资源存在）
     * @throws BusinessException
     */
    public static function throwUnless(mixed $value, string $msg, int $code = self::CODE_PARAM_ERROR): void
    {
        if (!$value) {
            throw new BusinessException($msg, $code);
        }
    }

    /**
     * 检查资源存在，不存在则抛 404
     * @throws BusinessException
     */
    public static function throwNotFound(mixed $value, string $msg = '资源不存在'): void
    {
        if (!$value) {
            throw new BusinessException($msg, self::CODE_NOT_FOUND);
        }
    }

    // ==================== 事务封装 ====================

    /**
     * 事务封装
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

    // ==================== 参数校验 ====================

    /**
     * 参数校验（校验失败抛 BusinessException）
     * @throws BusinessException
     */
    protected function validate(Request $request, array $rules, ?array $input = null): array
    {
        try {
            return v::input($input ?? $request->all(), $rules);
        } catch (ValidationException $e) {
            throw new BusinessException($e->getMessage(), self::CODE_PARAM_ERROR);
        }
    }

    /**
     * Lazy load dependencies for modules (Dep/Service).
     *
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    protected function dep(string $class)
    {
        if (!isset($this->instances[$class])) {
            $this->instances[$class] = new $class();
        }
        return $this->instances[$class];
    }

    /**
     * Alias for service dependencies.
     *
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    protected function svc(string $class)
    {
        return $this->dep($class);
    }

    // ==================== 异常转响应 ====================

    /**
     * 异常转统一响应格式（供 Controller 兜底使用）
     * 业务异常：只返回 msg，不带 trace
     * 未知异常：trace 写日志，不返回给前端
     */
    public static function fromException(\Throwable $e): array
    {
        if ($e instanceof BusinessException) {
            // 业务异常：msg 足够定位问题，不需要 trace
            return [[], $e->getCode(), $e->getMessage()];
        }
        
        // 未知异常：记录日志，返回通用错误信息
        // TODO: Log::error('Unexpected error', ['exception' => $e]);
        
        $msg = config('app.debug') ? $e->getMessage() : 'server error';
        return [[], self::CODE_SERVER_ERROR, $msg];
    }

    /**
     * 判断是否为唯一键冲突（MySQL 1062）
     */
    protected static function isDuplicateKey(\Throwable $e): bool
    {
        if ($e instanceof \PDOException && isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
            return true;
        }
        return str_contains($e->getMessage(), 'Duplicate entry');
    }

}

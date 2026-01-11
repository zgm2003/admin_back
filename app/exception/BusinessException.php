<?php

namespace app\exception;

use app\enum\ErrorCodeEnum;

/**
 * 业务异常
 * 用于在业务逻辑中抛出可预期的错误，会被全局异常处理器捕获并返回统一格式
 */
class BusinessException extends \Exception
{
    /**
     * @param string $message 错误消息
     * @param int $code 错误码（默认 100 参数错误）
     */
    public function __construct(string $message = '', int $code = ErrorCodeEnum::PARAM_ERROR)
    {
        parent::__construct($message, $code);
    }

    /**
     * 快捷方法：参数错误
     */
    public static function paramError(string $message = '参数错误'): self
    {
        return new self($message, ErrorCodeEnum::PARAM_ERROR);
    }

    /**
     * 快捷方法：未认证
     */
    public static function unauthorized(string $message = '请先登录'): self
    {
        return new self($message, ErrorCodeEnum::UNAUTHORIZED);
    }

    /**
     * 快捷方法：无权限
     */
    public static function forbidden(string $message = '无权限访问'): self
    {
        return new self($message, ErrorCodeEnum::FORBIDDEN);
    }

    /**
     * 快捷方法：资源不存在
     */
    public static function notFound(string $message = '资源不存在'): self
    {
        return new self($message, ErrorCodeEnum::NOT_FOUND);
    }

    /**
     * 快捷方法：服务器错误
     */
    public static function serverError(string $message = '服务器内部错误'): self
    {
        return new self($message, ErrorCodeEnum::SERVER_ERROR);
    }
}

<?php

namespace app\exception;

/**
 * 业务异常
 * 用于在 Module 层抛出可预期的业务错误，会被 Controller 层捕获并返回友好提示
 */
class BusinessException extends \Exception
{
    public function __construct(string $message, int $code = 100)
    {
        parent::__construct($message, $code);
    }
}

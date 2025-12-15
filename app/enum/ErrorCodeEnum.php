<?php

namespace app\enum;

class ErrorCodeEnum
{
    // 成功
    public const SUCCESS = 0;

    // 参数 & 业务
    public const PARAM_ERROR = 100;

    // 认证 & 权限
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN    = 403;

    // 资源
    public const NOT_FOUND    = 404;

    // 系统
    public const SERVER_ERROR = 500;
}

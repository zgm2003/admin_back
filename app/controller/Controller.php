<?php

namespace app\controller;

use support\Request;
use support\Response;

/**
 * 控制器基类
 * 异常由全局 Handler 捕获
 */
class Controller
{
    protected function run(array $call, Request $request): Response
    {
        [$data, $code, $msg] = (new $call[0])->{$call[1]}($request);
        
        return json(compact('code', 'data', 'msg'));
    }
}

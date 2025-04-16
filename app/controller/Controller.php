<?php

namespace app\controller;

use Illuminate\Database\Eloquent\Casts\Json;
use support\Request;
use support\Response;

class Controller
{
    private mixed $data = null;
    private int $code = 0;
    private string $msg = '';

    /**
     * 执行模块逻辑，并拆解结果
     * @param array $callArr [ClassName, MethodName]
     * @param Request $request
     * @param array $extra
     * @return void
     */
    public function run(array $callArr, Request $request, array $extra = []): void
    {
        $callArr[0] = new $callArr[0];
        $moduleRes = call_user_func_array($callArr, [$request, $extra]);
        [$this->data, $this->code, $this->msg] = $moduleRes;
    }

    /**
     * 返回结构化响应
     * @return Json
     */
    public function response(): Response
    {
        return json([
            "code" => $this->code,
            "data" => $this->data,
            "msg"  => $this->msg,
        ]);
    }
}

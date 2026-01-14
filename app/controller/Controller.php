<?php

namespace app\controller;

use app\exception\BusinessException;
use app\module\BaseModule;
use support\Request;
use support\Response;

class Controller
{
    private mixed $data = null;
    private int $code = 0;
    private string $msg = '';

    /**
     * 执行模块逻辑，并拆解结果
     */
    public function run(array $callArr, Request $request, array $extra = []): void
    {
        try {
            $callArr[0] = new $callArr[0];
            $moduleRes = call_user_func_array($callArr, [$request, $extra]);
            [$this->data, $this->code, $this->msg] = $moduleRes;
        } catch (BusinessException $e) {
            // 业务异常，返回友好提示
            [$this->data, $this->code, $this->msg] = BaseModule::fromException($e);
        } catch (\Throwable $e) {
            // 未知异常，记录日志并返回错误
            // TODO: Log::error('Unexpected error', ['exception' => $e]);
            [$this->data, $this->code, $this->msg] = BaseModule::fromException($e);
        }
    }

    /**
     * 返回结构化响应
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

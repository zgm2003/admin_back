<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiChatModule;
use app\validate\Ai\AiChatValidate;
use support\Request;
use support\Response;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Workerman\Protocols\Http\ServerSentEvents;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class AiChatController extends Controller
{
    /**
     * 发送消息并获取 AI 回复（非流式）
     */
    public function send(Request $request)
    {
        $this->run([AiChatModule::class, 'send'], $request);
        return $this->response();
    }

    /**
     * 发送消息并获取 AI 回复（流式 SSE）
     */
    public function stream(Request $request)
    {
        // 1. 参数校验
        $rules = AiChatValidate::send();
        try {
            $param = v::input($request->all(), $rules);
        } catch (ValidationException $e) {
            return $this->sseError($request, $e->getMessage());
        }

        $userId = $request->userId;
        $connection = $request->connection;

        // 2. 发送 SSE 响应头
        $connection->send(new WorkermanResponse(200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ], "\r\n"));

        // 3. 调用流式接口
        $module = new AiChatModule();
        $result = $module->sendStream($param, $userId, function ($event, $data) use ($connection) {
            // SSE 格式发送
            $connection->send(new ServerSentEvents([
                'event' => $event,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]));
        });

        // 4. 如果返回错误，发送 error 事件
        if ($result['code'] !== 0) {
            $connection->send(new ServerSentEvents([
                'event' => 'error',
                'data' => json_encode(['msg' => $result['msg']], JSON_UNESCAPED_UNICODE),
            ]));
        }

        // SSE 不需要返回 Response，返回 null
        return null;
    }

    /**
     * SSE 错误响应
     */
    private function sseError(Request $request, string $msg)
    {
        $connection = $request->connection;
        $connection->send(new WorkermanResponse(200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ], "\r\n"));
        $connection->send(new ServerSentEvents([
            'event' => 'error',
            'data' => json_encode(['msg' => $msg], JSON_UNESCAPED_UNICODE),
        ]));
        return null;
    }
}

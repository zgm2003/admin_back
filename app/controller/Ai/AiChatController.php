<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\middleware\AccessControl;
use app\module\Ai\AiChatModule;
use app\validate\Ai\AiChatValidate;
use support\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Workerman\Protocols\Http\ServerSentEvents;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class AiChatController extends Controller
{
    public function send(Request $request) { return $this->run([AiChatModule::class, 'send'], $request); }
    public function cancel(Request $request) { return $this->run([AiChatModule::class, 'cancel'], $request); }

    /**
     * 获取 SSE CORS 响应头
     */
    private function getSseHeaders(Request $request): array
    {
        return array_merge([
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Vary' => 'Origin',
        ], AccessControl::buildOriginHeaders($request->header('origin')));
    }

    /**
     * 发送消息并获取 AI 回复（流式 SSE）
     */
    public function stream(Request $request)
    {
        $rules = AiChatValidate::send();
        try {
            $param = v::input($request->all(), $rules);
        } catch (ValidationException $e) {
            return $this->sseError($request, $e->getMessage());
        }

        $userId = $request->userId;
        $connection = $request->connection;

        $connection->send(new WorkermanResponse(200, $this->getSseHeaders($request), "\r\n"));

        $module = new AiChatModule();
        $result = $module->sendStream($param, $userId, function ($event, $data) use ($connection) {
            $connection->send(new ServerSentEvents([
                'event' => $event,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]));
        });

        if ($result[1] !== 0) {
            $connection->send(new ServerSentEvents([
                'event' => 'error',
                'data' => json_encode(['msg' => $result[2]], JSON_UNESCAPED_UNICODE),
            ]));
        }

        return null;
    }

    /**
     * SSE 错误响应
     */
    private function sseError(Request $request, string $msg)
    {
        $connection = $request->connection;
        $connection->send(new WorkermanResponse(200, $this->getSseHeaders($request), "\r\n"));
        $connection->send(new ServerSentEvents([
            'event' => 'error',
            'data' => json_encode(['msg' => $msg], JSON_UNESCAPED_UNICODE),
        ]));
        return null;
    }
}

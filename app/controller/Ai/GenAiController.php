<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\middleware\AccessControl;
use app\module\Ai\GenAiModule;
use support\Request;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Workerman\Protocols\Http\ServerSentEvents;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class GenAiController extends Controller
{
    /**
     * 获取代码生成配置（可用智能体列表等）
     */
    public function init(Request $request)
    {
        return $this->run([GenAiModule::class, 'init'], $request);
    }

    /**
     * 获取会话历史列表
     */
    public function conversations(Request $request)
    {
        return $this->run([GenAiModule::class, 'conversations'], $request);
    }

    /**
     * 获取指定会话的消息列表
     */
    public function messages(Request $request)
    {
        return $this->run([GenAiModule::class, 'messages'], $request);
    }

    /**
     * 删除会话
     */
    public function deleteConversation(Request $request)
    {
        return $this->run([GenAiModule::class, 'deleteConversation'], $request);
    }

    /**
     * AI 代码生成（流式 SSE）
     */
    public function stream(Request $request)
    {
        $rules = [
            'content' => v::stringType()->length(1, 10000)->setName('需求描述'),
        ];

        try {
            $param = v::input($request->all(), $rules);
        } catch (ValidationException $e) {
            return $this->sseError($request, $e->getMessage());
        }

        $param = $request->all();
        $userId = $request->userId;
        $connection = $request->connection;

        $connection->send(new WorkermanResponse(200, $this->getSseHeaders($request), "\r\n"));

        $module = new GenAiModule();
        try {
            $module->stream($param, $userId, function ($event, $data) use ($connection) {
                $connection->send(new ServerSentEvents([
                    'event' => $event,
                    'data'  => json_encode($data, JSON_UNESCAPED_UNICODE),
                ]));
            });
        } catch (\Throwable $e) {
            $connection->send(new ServerSentEvents([
                'event' => 'error',
                'data'  => json_encode(['msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]));
        }

        return null;
    }

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
     * SSE 错误响应
     */
    private function sseError(Request $request, string $msg)
    {
        $connection = $request->connection;
        $connection->send(new WorkermanResponse(200, $this->getSseHeaders($request), "\r\n"));
        $connection->send(new ServerSentEvents([
            'event' => 'error',
            'data'  => json_encode(['msg' => $msg], JSON_UNESCAPED_UNICODE),
        ]));
        return null;
    }
}

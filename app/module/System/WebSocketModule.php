<?php

namespace app\module\System;

use app\module\BaseModule;
use app\validate\System\WebSocketValidate;
use GatewayWorker\Lib\Gateway;

/**
 * WebSocket 模块
 * 处理 WebSocket 绑定和推送
 */
class WebSocketModule extends BaseModule
{
    public function __construct()
    {
        Gateway::$registerAddress = '127.0.0.1:1236';
    }

    public function bind($request): array
    {
        $param = $this->validate($request, WebSocketValidate::bind());
        $userId = $request->userId;

        Gateway::bindUid($param['client_id'], $userId);
        Gateway::sendToClient($param['client_id'], json_encode([
            'type' => 'bind_success',
            'data' => ['uid' => $userId]
        ]));

        // 记录用户上线
        \support\Log::info("[WebSocket] 用户上线: uid={$userId}, client_id={$param['client_id']}");

        return self::success(['bound' => true]);
    }

    public function joinGroup($request): array
    {
        $param = $this->validate($request, WebSocketValidate::joinGroup());

        Gateway::joinGroup($param['client_id'], $param['group']);

        return self::success(['joined' => true]);
    }

    public function onlineCount($request): array
    {
        return self::success([
            'total' => Gateway::getAllClientIdCount()
        ]);
    }

    public function pushToUser($request): array
    {
        $param = $this->validate($request, WebSocketValidate::pushToUser());

        Gateway::sendToUid($param['uid'], json_encode([
            'type' => $param['type'] ?? 'notification',
            'data' => $param['data'] ?? []
        ]));

        return self::success(['sent' => true]);
    }

    public function broadcast($request): array
    {
        $param = $this->validate($request, WebSocketValidate::broadcast());

        Gateway::sendToAll(json_encode([
            'type' => $param['type'] ?? 'broadcast',
            'data' => $param['data'] ?? []
        ]));

        return self::success(['sent' => true]);
    }
}

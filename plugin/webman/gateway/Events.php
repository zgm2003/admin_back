<?php

namespace plugin\webman\gateway;

use GatewayWorker\Lib\Gateway;

/**
 * GatewayWorker 事件处理
 * 注意：Gateway 只做消息转发，业务逻辑通过 HTTP 接口 + GatewayClient 处理
 */
class Events
{
    public static function buildInitPayload(string $clientId): array
    {
        return [
            'type' => 'init',
            'data' => [
                'client_id' => $clientId,
            ],
        ];
    }

    public static function onWorkerStart($worker)
    {
    }

    public static function onConnect($client_id)
    {
        Gateway::sendToClient($client_id, json_encode(self::buildInitPayload($client_id), JSON_UNESCAPED_UNICODE));
    }

    public static function onWebSocketConnect($client_id, $data)
    {
    }

    public static function onMessage($client_id, $message)
    {
    }

    public static function onClose($client_id)
    {
    }
}

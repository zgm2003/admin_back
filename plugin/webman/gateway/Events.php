<?php

namespace plugin\webman\gateway;

use GatewayWorker\Lib\Gateway;

class Events
{
    public static function onWorkerStart($worker)
    {

    }

    public static function onConnect($client_id)
    {
        Gateway::sendToClient($client_id, json_encode(array(
            'type'      => 'init',
            'client_id' => $client_id
        )));
    }

    public static function onWebSocketConnect($client_id, $data)
    {

    }

    public static function onMessage($client_id, $message)
    {

    }

    public static function onClose($client_id)
    {
        // 向所有人发送
        $data = [
            'type' => 'logout',
            'client_id' => $client_id
        ];
        GateWay::sendToAll(json_encode($data));
    }

}

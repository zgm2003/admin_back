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
    public static function buildEventPayload(string $type, array $data = []): array
    {
        return [
            'type' => $type,
            'data' => $data,
        ];
    }

    public function __construct()
    {
        Gateway::$registerAddress = '127.0.0.1:1236';
    }

    public function bind($request): array
    {
        $param = $this->validate($request, WebSocketValidate::bind());
        $userId = $request->userId;
        $platform = $request->platform ?? 'admin';

        // 绑定用户
        Gateway::bindUid($param['client_id'], $userId);
        
        // 加入平台组（用于平台隔离推送）
        Gateway::joinGroup($param['client_id'], "platform_{$platform}_{$userId}");
        
        Gateway::sendToClient(
            $param['client_id'],
            json_encode(
                self::buildEventPayload('bind_success', ['uid' => $userId, 'platform' => $platform]),
                JSON_UNESCAPED_UNICODE
            )
        );

//        \support\Log::info("[WebSocket] 用户上线: uid={$userId}, platform={$platform}, client_id={$param['client_id']}");

        return self::success(['bound' => true, 'platform' => $platform]);
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

        Gateway::sendToUid(
            $param['uid'],
            json_encode(
                self::buildEventPayload($param['type'] ?? 'notification', $param['data'] ?? []),
                JSON_UNESCAPED_UNICODE
            )
        );

        return self::success(['sent' => true]);
    }

    public function broadcast($request): array
    {
        $param = $this->validate($request, WebSocketValidate::broadcast());

        Gateway::sendToAll(
            json_encode(
                self::buildEventPayload($param['type'] ?? 'broadcast', $param['data'] ?? []),
                JSON_UNESCAPED_UNICODE
            )
        );

        return self::success(['sent' => true]);
    }

    /**
     * 测试平台推送
     * platform: all=所有平台, admin=仅后台, app=仅APP
     */
    public function testPlatformPush($request): array
    {
        $userId = $request->userId;
        $platform = $request->post('platform', 'all');
        $title = $request->post('title', '测试推送');
        
        $notificationId = \app\service\System\NotificationService::send(
            $userId,
            $title,
            "平台: {$platform}, 时间: " . date('H:i:s'),
            ['platform' => $platform]
        );

        return self::success([
            'notification_id' => $notificationId,
            'platform' => $platform,
            'user_id' => $userId
        ]);
    }
}

<?php

namespace app\service\System;

use app\dep\System\NotificationDep;
use app\enum\CommonEnum;
use GatewayWorker\Lib\Gateway;

/**
 * 通知服务
 * 用于发送通知（写入数据库 + WebSocket 推送）
 */
class NotificationService
{
    // 通知类型
    const TYPE_INFO = 'info';
    const TYPE_SUCCESS = 'success';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR = 'error';
    
    // 通知级别
    const LEVEL_NORMAL = 'normal';   // 静默，只更新角标
    const LEVEL_URGENT = 'urgent';   // 紧急，弹 Toast

    /**
     * 发送通知给指定用户
     */
    public static function send(int $userId, string $title, string $content = '', array $options = []): int
    {
        Gateway::$registerAddress = '127.0.0.1:1236';
        
        $type = $options['type'] ?? self::TYPE_INFO;
        $level = $options['level'] ?? self::LEVEL_NORMAL;
        $link = $options['link'] ?? '';

        // 写入数据库
        $notificationDep = new NotificationDep();
        $notification = $notificationDep->create([
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'level' => $level,
            'link' => $link,
            'is_read' => CommonEnum::NO,
            'is_del' => CommonEnum::NO,
        ]);

        // WebSocket 推送
        try {
            Gateway::sendToUid($userId, json_encode([
                'type' => 'notification',
                'data' => [
                    'id' => $notification->id,
                    'title' => $title,
                    'content' => $content,
                    'notification_type' => $type,
                    'level' => $level,
                    'link' => $link,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            ]));
        } catch (\Throwable $e) {
            \support\Log::warning("[NotificationService] WebSocket 推送失败: " . $e->getMessage());
        }

        return $notification->id;
    }

    /** 发送紧急通知（会弹 Toast） */
    public static function sendUrgent(int $userId, string $title, string $content = '', array $options = []): int
    {
        return self::send($userId, $title, $content, ['level' => self::LEVEL_URGENT] + $options);
    }

    /** 发送成功通知 */
    public static function sendSuccess(int $userId, string $title, string $content = '', array $options = []): int
    {
        return self::send($userId, $title, $content, ['type' => self::TYPE_SUCCESS] + $options);
    }

    /** 发送警告通知 */
    public static function sendWarning(int $userId, string $title, string $content = '', array $options = []): int
    {
        return self::send($userId, $title, $content, ['type' => self::TYPE_WARNING] + $options);
    }

    /** 发送错误通知 */
    public static function sendError(int $userId, string $title, string $content = '', array $options = []): int
    {
        return self::send($userId, $title, $content, ['type' => self::TYPE_ERROR] + $options);
    }
}

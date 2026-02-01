<?php

namespace app\service\System;

use app\dep\System\NotificationDep;
use app\enum\CommonEnum;
use app\enum\NotificationEnum;
use GatewayWorker\Lib\Gateway;

/**
 * 通知服务
 * 用于发送通知（写入数据库 + WebSocket 推送）
 */
class NotificationService
{
    // 通知类型（字符串，用于 notifications 表）
    const TYPE_INFO = 'info';
    const TYPE_SUCCESS = 'success';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR = 'error';
    
    // 通知级别（字符串，用于 notifications 表）
    const LEVEL_NORMAL = 'normal';
    const LEVEL_URGENT = 'urgent';

    /**
     * 发送通知给指定用户
     * 
     * @param int $userId 用户ID
     * @param string $title 标题
     * @param string $content 内容
     * @param array $options 选项
     *   - type: 通知类型 (info/success/warning/error)
     *   - level: 通知级别 (normal/urgent)
     *   - link: 跳转链接
     *   - platform: 推送平台 ('all'=所有平台, 'admin'=仅后台, 'app'=仅APP)
     */
    public static function send(int $userId, string $title, string $content = '', array $options = []): int
    {
        Gateway::$registerAddress = '127.0.0.1:1236';
        
        $type = $options['type'] ?? self::TYPE_INFO;
        $level = $options['level'] ?? self::LEVEL_NORMAL;
        $link = $options['link'] ?? '';
        $platform = $options['platform'] ?? 'all'; // 默认推送所有平台（适合聊天等场景）

        // 写入数据库
        $notificationDep = new NotificationDep();
        $notificationId = $notificationDep->add([
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'level' => $level,
            'link' => $link,
            'platform' => $platform,
            'is_read' => CommonEnum::NO,
            'is_del' => CommonEnum::NO,
        ]);

        // WebSocket 推送
        $message = json_encode([
            'type' => 'notification',
            'data' => [
                'id' => $notificationId,
                'title' => $title,
                'content' => $content,
                'notification_type' => $type,
                'level' => $level,
                'link' => $link,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ]);
        
        try {
            if ($platform === 'all') {
                // 推送到所有平台（聊天消息、重要通知等）
                Gateway::sendToUid($userId, $message);
            } else {
                // 推送到特定平台（导出完成等场景）
                Gateway::sendToGroup("platform_{$platform}_{$userId}", $message);
            }
        } catch (\Throwable $e) {
            \support\Log::warning("[NotificationService] WebSocket 推送失败: platform={$platform}, " . $e->getMessage());
        }

        return $notificationId;
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

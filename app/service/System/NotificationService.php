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
    /**
     * 发送通知给指定用户
     *
     * @param int $userId 用户ID
     * @param string $title 标题
     * @param string $content 内容
     * @param array $options 选项
     *   - type: 通知类型 (NotificationEnum::TYPE_*)
     *   - level: 通知级别 (NotificationEnum::LEVEL_*)
     *   - link: 跳转链接
     *   - platform: 推送平台 ('all'/'admin'/'app')
     */
    public static function send(int $userId, string $title, string $content = '', array $options = []): int
    {
        Gateway::$registerAddress = '127.0.0.1:1236';

        $type = $options['type'] ?? NotificationEnum::TYPE_INFO;
        $level = $options['level'] ?? NotificationEnum::LEVEL_NORMAL;
        $link = $options['link'] ?? '';
        $platform = $options['platform'] ?? 'all';

        // 写入数据库（created_at / updated_at 由 MySQL DEFAULT 自动维护）
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
                'notification_type' => NotificationEnum::getTypeStr($type),
                'level' => NotificationEnum::getLevelStr($level),
                'link' => $link,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ]);

        try {
            if ($platform === 'all') {
                Gateway::sendToUid($userId, $message);
            } else {
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
        return self::send($userId, $title, $content, ['level' => NotificationEnum::LEVEL_URGENT] + $options);
    }

    /** 发送成功通知 */
    public static function sendSuccess(int $userId, string $title, string $content = '', array $options = []): int
    {
        return self::send($userId, $title, $content, ['type' => NotificationEnum::TYPE_SUCCESS] + $options);
    }

    /** 发送警告通知 */
    public static function sendWarning(int $userId, string $title, string $content = '', array $options = []): int
    {
        return self::send($userId, $title, $content, ['type' => NotificationEnum::TYPE_WARNING] + $options);
    }

    /** 发送错误通知 */
    public static function sendError(int $userId, string $title, string $content = '', array $options = []): int
    {
        return self::send($userId, $title, $content, ['type' => NotificationEnum::TYPE_ERROR] + $options);
    }
}

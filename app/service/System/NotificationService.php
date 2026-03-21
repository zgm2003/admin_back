<?php

namespace app\service\System;

use app\dep\System\NotificationDep;
use app\enum\CommonEnum;
use app\enum\NotificationEnum;
use GatewayWorker\Lib\Gateway;

/**
 * 通知服务
 * 负责：发送通知（写入数据库 + WebSocket 实时推送）
 * 支持按平台推送（all 或任意已配置平台码），支持紧急/成功/警告/错误等快捷方法
 */
class NotificationService
{
    private static ?NotificationDep $dep = null;

    private static function dep(): NotificationDep
    {
        return self::$dep ??= new NotificationDep();
    }

    /**
     * 发送通知给指定用户（写入 DB + WebSocket 推送）
     *
     * @param int    $userId  用户ID
     * @param string $title   标题
     * @param string $content 内容
     * @param array  $options 选项：type / level / link / platform
     * @return int 通知ID
     */
    public static function send(int $userId, string $title, string $content = '', array $options = []): int
    {
        Gateway::$registerAddress = '127.0.0.1:1236';

        $type     = $options['type'] ?? NotificationEnum::TYPE_INFO;
        $level    = $options['level'] ?? NotificationEnum::LEVEL_NORMAL;
        $link     = $options['link'] ?? '';
        $platform = $options['platform'] ?? 'all';

        // 写入数据库（created_at / updated_at 由 MySQL DEFAULT 自动维护）
        $notificationId = self::dep()->add([
            'user_id'  => $userId,
            'title'    => $title,
            'content'  => $content,
            'type'     => $type,
            'level'    => $level,
            'link'     => $link,
            'platform' => $platform,
            'is_read'  => CommonEnum::NO,
            'is_del'   => CommonEnum::NO,
        ]);

        // WebSocket 推送（失败仅记录日志，不影响主流程）
        self::pushWebSocket($userId, $platform, [
            'id'                => $notificationId,
            'title'             => $title,
            'content'           => $content,
            'notification_type' => NotificationEnum::getTypeStr($type),
            'level'             => NotificationEnum::getLevelStr($level),
            'link'              => $link,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

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

    // ==================== 私有方法 ====================

    /**
     * WebSocket 推送（按平台路由：all → sendToUid，指定平台 → sendToGroup）
     */
    private static function pushWebSocket(int $userId, string $platform, array $data): void
    {
        $message = json_encode(['type' => 'notification', 'data' => $data], JSON_UNESCAPED_UNICODE);

        try {
            if ($platform === 'all') {
                Gateway::sendToUid($userId, $message);
            } else {
                Gateway::sendToGroup("platform_{$platform}_{$userId}", $message);
            }
        } catch (\Throwable $e) {
            \support\Log::warning("[NotificationService] WebSocket 推送失败: platform={$platform}, {$e->getMessage()}");
        }
    }
}

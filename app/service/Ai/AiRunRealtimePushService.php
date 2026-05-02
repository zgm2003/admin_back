<?php

namespace app\service\Ai;

use app\dep\Ai\AiRunsDep;
use app\enum\CommonEnum;
use GatewayWorker\Lib\Gateway;
use support\Log;

class AiRunRealtimePushService
{
    private const REGISTER_ADDRESS = '127.0.0.1:1236';

    private static array $runUserIdCache = [];

    public function pushRunEvent(int $runId, string $eventId, string $event, array $payload): void
    {
        try {
            $userId = $this->resolveUserId($runId);
            if ($userId === null) {
                return;
            }

            $message = json_encode(
                self::buildRunEventPayload($runId, $eventId, $event, $payload),
                JSON_UNESCAPED_UNICODE
            );
            if (!is_string($message)) {
                Log::warning("[AiRunRealtimePushService] WebSocket 消息编码失败: runId={$runId}");
                return;
            }

            Gateway::$registerAddress = self::REGISTER_ADDRESS;
            Gateway::sendToUid((string)$userId, $message);
        } catch (\Throwable $e) {
            Log::warning("[AiRunRealtimePushService] WebSocket 推送失败: runId={$runId}, {$e->getMessage()}");
        }
    }

    public static function buildRunEventPayload(int $runId, string $eventId, string $event, array $payload): array
    {
        return [
            'type' => 'ai_run_event',
            'data' => [
                'run_id' => $runId,
                'event_id' => $eventId,
                'event' => $event,
                'payload' => $payload,
            ],
        ];
    }

    private function resolveUserId(int $runId): ?int
    {
        if (array_key_exists($runId, self::$runUserIdCache)) {
            return self::$runUserIdCache[$runId];
        }

        $run = (new AiRunsDep())->find($runId);
        if (!$run || (int)$run->is_del !== CommonEnum::NO) {
            return self::$runUserIdCache[$runId] = null;
        }

        return self::$runUserIdCache[$runId] = (int)$run->user_id;
    }
}

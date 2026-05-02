<?php

namespace app\service\Ai;

use support\Redis;

class AiRunEventPublisher
{
    private const KEY_PREFIX = 'ai:run:';
    private const KEY_SUFFIX = ':events';
    private const TTL_SECONDS = 86400;
    private const BLOCK_MS = 1000;

    private AiRunRealtimePushService $realtimePush;

    public function __construct(?AiRunRealtimePushService $realtimePush = null)
    {
        $this->realtimePush = $realtimePush ?? new AiRunRealtimePushService();
    }

    public function publish(int $runId, string $event, array $data = []): string
    {
        $key = $this->key($runId);
        $id = (string)Redis::xAdd($key, '*', [
            'event' => $event,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'created_at' => (string)time(),
        ]);
        Redis::expire($key, self::TTL_SECONDS);

        if ($id !== '') {
            $this->realtimePush->pushRunEvent($runId, $id, $event, $data);
        }

        return $id;
    }

    public function publishError(int $runId, string $message, array $extra = []): string
    {
        return $this->publish($runId, 'error', array_merge(['msg' => $message], $extra));
    }

    /**
     * @return array<int, array{id:string,event:string,data:array}>
     */
    public function read(int $runId, string $lastId = '0-0', ?int $blockMs = self::BLOCK_MS): array
    {
        $key = $this->key($runId);
        $result = $blockMs === null
            ? Redis::xRead([$key => $lastId], 10)
            : Redis::xRead([$key => $lastId], 10, $blockMs);
        if (!is_array($result)) {
            return [];
        }

        $rows = $result[$key] ?? [];
        $events = [];

        foreach ($rows as $id => $row) {
            $decoded = json_decode((string)($row['data'] ?? '{}'), true);
            $events[] = [
                'id' => (string)$id,
                'event' => (string)($row['event'] ?? 'message'),
                'data' => is_array($decoded) ? $decoded : [],
            ];
        }

        return $events;
    }

    public function hasAnyEvent(int $runId): bool
    {
        return $this->read($runId, '0-0', null) !== [];
    }

    public function key(int $runId): string
    {
        return self::KEY_PREFIX . '{' . $runId . '}' . self::KEY_SUFFIX;
    }
}

<?php

namespace app\service\Ai;

use support\Redis;

/**
 * AI 流式输出缓存服务
 * 使用 Redis 缓存流式内容，支持断线重连和会话切换后恢复
 * 
 * 存储结构：
 * - ai_stream:{runId}:meta (HASH) - 元数据
 * - ai_stream:{runId}:content (STRING) - 内容，使用 APPEND 原子追加
 */
class AiStreamCacheService
{
    // Redis key 前缀
    const PREFIX = 'ai_stream:';
    
    // 缓存过期时间（秒）
    const TTL = 600; // 10 分钟
    
    // 状态常量
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAIL = 'fail';
    const STATUS_CANCELED = 'canceled';

    private static function metaKey(int $runId): string
    {
        return self::PREFIX . $runId . ':meta';
    }

    private static function contentKey(int $runId): string
    {
        return self::PREFIX . $runId . ':content';
    }

    /**
     * 初始化流式缓存（开始流式输出时调用）
     */
    public static function init(int $runId, array $meta = []): void
    {
        $redis = Redis::connection('default');
        $metaKey = self::metaKey($runId);
        $contentKey = self::contentKey($runId);
        
        // 元数据用 HASH
        $redis->hMSet($metaKey, [
            'status' => self::STATUS_RUNNING,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'started_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $redis->expire($metaKey, self::TTL);
        
        // 内容用 STRING，初始化为空
        $redis->set($contentKey, '');
        $redis->expire($contentKey, self::TTL);
    }

    /**
     * 追加流式内容（每收到一个 chunk 调用）
     * 使用 Redis APPEND 命令，原子操作，并发安全
     */
    public static function append(int $runId, string $delta): void
    {
        $redis = Redis::connection('default');
        
        // APPEND 是原子操作，多进程并发安全
        $redis->append(self::contentKey($runId), $delta);
        
        // 更新时间和续期
        $metaKey = self::metaKey($runId);
        $redis->hSet($metaKey, 'updated_at', date('Y-m-d H:i:s'));
        $redis->expire($metaKey, self::TTL);
        $redis->expire(self::contentKey($runId), self::TTL);
    }

    /**
     * 标记完成（流式输出结束时调用）
     */
    public static function markSuccess(int $runId, array $result = []): void
    {
        $redis = Redis::connection('default');
        $metaKey = self::metaKey($runId);
        
        $redis->hMSet($metaKey, [
            'status' => self::STATUS_SUCCESS,
            'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        
        // 完成后保留 2 分钟供前端获取
        $redis->expire($metaKey, 120);
        $redis->expire(self::contentKey($runId), 120);
    }

    /**
     * 标记取消
     */
    public static function markCanceled(int $runId): void
    {
        $redis = Redis::connection('default');
        $metaKey = self::metaKey($runId);
        
        $redis->hMSet($metaKey, [
            'status' => self::STATUS_CANCELED,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        
        // 取消后保留 2 分钟
        $redis->expire($metaKey, 120);
        $redis->expire(self::contentKey($runId), 120);
    }

    /**
     * 检查是否已取消
     */
    public static function isCanceled(int $runId): bool
    {
        $redis = Redis::connection('default');
        $status = $redis->hGet(self::metaKey($runId), 'status');
        return $status === self::STATUS_CANCELED;
    }

    /**
     * 标记失败
     */
    public static function markFailed(int $runId, string $errorMsg): void
    {
        $redis = Redis::connection('default');
        $metaKey = self::metaKey($runId);
        
        $redis->hMSet($metaKey, [
            'status' => self::STATUS_FAIL,
            'error_msg' => $errorMsg,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        
        // 失败后保留 2 分钟
        $redis->expire($metaKey, 120);
        $redis->expire(self::contentKey($runId), 120);
    }

    /**
     * 获取缓存数据（用于恢复/续传）
     */
    public static function get(int $runId): ?array
    {
        $redis = Redis::connection('default');
        $metaKey = self::metaKey($runId);
        
        $meta = $redis->hGetAll($metaKey);
        if (empty($meta)) {
            return null;
        }
        
        $content = $redis->get(self::contentKey($runId)) ?: '';
        
        return [
            'content' => $content,
            'status' => $meta['status'] ?? self::STATUS_FAIL,
            'meta' => json_decode($meta['meta'] ?? '{}', true),
            'result' => json_decode($meta['result'] ?? '{}', true),
            'error_msg' => $meta['error_msg'] ?? null,
            'started_at' => $meta['started_at'] ?? null,
            'updated_at' => $meta['updated_at'] ?? null,
            'finished_at' => $meta['finished_at'] ?? null,
        ];
    }

    /**
     * 获取当前内容长度（用于续传时确定偏移量）
     */
    public static function getContentLength(int $runId): int
    {
        $redis = Redis::connection('default');
        // STRLEN 是原子操作
        return (int) $redis->strlen(self::contentKey($runId));
    }

    /**
     * 检查是否存在
     */
    public static function exists(int $runId): bool
    {
        return (bool) Redis::connection('default')->exists(self::metaKey($runId));
    }

    /**
     * 删除缓存
     */
    public static function delete(int $runId): void
    {
        $redis = Redis::connection('default');
        $redis->del(self::metaKey($runId));
        $redis->del(self::contentKey($runId));
    }

    /**
     * 订阅内容更新（用于续传 SSE）
     * 使用 GETRANGE 原子获取增量内容，并发安全
     */
    public static function subscribe(int $runId, int $offset = 0, int $pollInterval = 100, int $timeout = 300): \Generator
    {
        $redis = Redis::connection('default');
        $metaKey = self::metaKey($runId);
        $contentKey = self::contentKey($runId);
        $startTime = time();
        $lastOffset = $offset;
        
        while (true) {
            // 超时检查
            if (time() - $startTime > $timeout) {
                yield ['type' => 'timeout'];
                break;
            }
            
            // 检查是否存在
            if (!$redis->exists($metaKey)) {
                yield ['type' => 'not_found'];
                break;
            }
            
            // 获取当前内容长度
            $currentLength = (int) $redis->strlen($contentKey);
            
            // 有新内容，使用 GETRANGE 原子获取增量
            if ($currentLength > $lastOffset) {
                $delta = $redis->getRange($contentKey, $lastOffset, $currentLength - 1);
                $lastOffset = $currentLength;
                yield ['type' => 'content', 'delta' => $delta];
            }
            
            // 获取状态
            $status = $redis->hGet($metaKey, 'status');
            
            // 检查是否完成
            if ($status === self::STATUS_SUCCESS) {
                $result = $redis->hGet($metaKey, 'result');
                yield [
                    'type' => 'done',
                    'result' => json_decode($result ?: '{}', true),
                ];
                break;
            }
            
            if ($status === self::STATUS_FAIL) {
                $errorMsg = $redis->hGet($metaKey, 'error_msg');
                yield [
                    'type' => 'error',
                    'error_msg' => $errorMsg ?: '未知错误',
                ];
                break;
            }
            
            if ($status === self::STATUS_CANCELED) {
                yield ['type' => 'canceled'];
                break;
            }
            
            // 等待下次轮询
            usleep($pollInterval * 1000);
        }
    }
}

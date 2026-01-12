<?php

namespace app\service\Ai;

use support\Redis;

/**
 * AI 流式输出缓存服务
 * 使用 Redis 缓存流式内容，支持断线重连和会话切换后恢复
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

    /**
     * 初始化流式缓存（开始流式输出时调用）
     */
    public static function init(int $runId, array $meta = []): void
    {
        $key = self::PREFIX . $runId;
        $redis = Redis::connection('default');
        
        // 使用 HASH 存储多个字段
        $redis->hMSet($key, [
            'content' => '',
            'status' => self::STATUS_RUNNING,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'started_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $redis->expire($key, self::TTL);
    }

    /**
     * 追加流式内容（每收到一个 chunk 调用）
     */
    public static function append(int $runId, string $delta): void
    {
        $key = self::PREFIX . $runId;
        $redis = Redis::connection('default');
        
        // 追加内容
        $redis->hSet($key, 'content', $redis->hGet($key, 'content') . $delta);
        $redis->hSet($key, 'updated_at', date('Y-m-d H:i:s'));
        
        // 续期
        $redis->expire($key, self::TTL);
    }

    /**
     * 标记完成（流式输出结束时调用）
     */
    public static function markSuccess(int $runId, array $result = []): void
    {
        $key = self::PREFIX . $runId;
        $redis = Redis::connection('default');
        
        $redis->hMSet($key, [
            'status' => self::STATUS_SUCCESS,
            'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        
        // 完成后保留 2 分钟供前端获取
        $redis->expire($key, 120);
    }

    /**
     * 标记取消
     */
    public static function markCanceled(int $runId): void
    {
        $key = self::PREFIX . $runId;
        $redis = Redis::connection('default');
        
        $redis->hMSet($key, [
            'status' => self::STATUS_CANCELED,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        
        // 取消后保留 2 分钟
        $redis->expire($key, 120);
    }

    /**
     * 检查是否已取消
     */
    public static function isCanceled(int $runId): bool
    {
        $key = self::PREFIX . $runId;
        $redis = Redis::connection('default');
        
        $status = $redis->hGet($key, 'status');
        return $status === self::STATUS_CANCELED;
    }

    /**
     * 标记失败
     */
    public static function markFailed(int $runId, string $errorMsg): void
    {
        $key = self::PREFIX . $runId;
        $redis = Redis::connection('default');
        
        $redis->hMSet($key, [
            'status' => self::STATUS_FAIL,
            'error_msg' => $errorMsg,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        
        // 失败后保留 2 分钟
        $redis->expire($key, 120);
    }

    /**
     * 获取缓存数据（用于恢复/续传）
     */
    public static function get(int $runId): ?array
    {
        $key = self::PREFIX . $runId;
        $redis = Redis::connection('default');
        
        $data = $redis->hGetAll($key);
        if (empty($data)) {
            return null;
        }
        
        return [
            'content' => $data['content'] ?? '',
            'status' => $data['status'] ?? self::STATUS_FAIL,
            'meta' => json_decode($data['meta'] ?? '{}', true),
            'result' => json_decode($data['result'] ?? '{}', true),
            'error_msg' => $data['error_msg'] ?? null,
            'started_at' => $data['started_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
            'finished_at' => $data['finished_at'] ?? null,
        ];
    }

    /**
     * 获取当前内容长度（用于续传时确定偏移量）
     */
    public static function getContentLength(int $runId): int
    {
        $key = self::PREFIX . $runId;
        $redis = Redis::connection('default');
        
        $content = $redis->hGet($key, 'content');
        return $content ? strlen($content) : 0;
    }

    /**
     * 检查是否存在
     */
    public static function exists(int $runId): bool
    {
        $key = self::PREFIX . $runId;
        return (bool) Redis::connection('default')->exists($key);
    }

    /**
     * 删除缓存
     */
    public static function delete(int $runId): void
    {
        $key = self::PREFIX . $runId;
        Redis::connection('default')->del($key);
    }

    /**
     * 订阅内容更新（用于续传 SSE）
     * 返回生成器，持续产出新增内容
     * 
     * @param int $runId
     * @param int $offset 起始偏移量（已接收的内容长度）
     * @param int $pollInterval 轮询间隔（毫秒）
     * @param int $timeout 超时时间（秒）
     */
    public static function subscribe(int $runId, int $offset = 0, int $pollInterval = 100, int $timeout = 300): \Generator
    {
        $key = self::PREFIX . $runId;
        $redis = Redis::connection('default');
        $startTime = time();
        $lastLength = $offset;
        
        while (true) {
            // 超时检查
            if (time() - $startTime > $timeout) {
                yield ['type' => 'timeout'];
                break;
            }
            
            // 获取当前状态和内容
            $data = $redis->hGetAll($key);
            if (empty($data)) {
                yield ['type' => 'not_found'];
                break;
            }
            
            $content = $data['content'] ?? '';
            $status = $data['status'] ?? self::STATUS_FAIL;
            $currentLength = strlen($content);
            
            // 有新内容，产出增量
            if ($currentLength > $lastLength) {
                $delta = substr($content, $lastLength);
                $lastLength = $currentLength;
                yield ['type' => 'content', 'delta' => $delta];
            }
            
            // 检查是否完成或取消
            if ($status === self::STATUS_SUCCESS) {
                yield [
                    'type' => 'done',
                    'result' => json_decode($data['result'] ?? '{}', true),
                ];
                break;
            }
            
            if ($status === self::STATUS_FAIL) {
                yield [
                    'type' => 'error',
                    'error_msg' => $data['error_msg'] ?? '未知错误',
                ];
                break;
            }
            
            if ($status === self::STATUS_CANCELED) {
                yield [
                    'type' => 'canceled',
                ];
                break;
            }
            
            // 等待下次轮询
            usleep($pollInterval * 1000);
        }
    }
}

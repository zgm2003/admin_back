<?php
namespace app\lib;

use support\Redis;

/**
 * 性能指标收集器
 * 收集 API/SSE/Queue 的 QPS、延迟、错误率等指标
 */
class Metrics
{
    // Redis key 前缀
    private const PREFIX = 'metrics:';
    
    // 指标保留时间（秒）
    private const TTL = 3600;
    
    /**
     * 记录 API 请求指标
     */
    public static function recordApi(string $path, string $method, int $statusCode, float $durationMs): void
    {
        $minute = date('YmdHi');
        $key = self::PREFIX . "api:{$minute}";
        
        try {
            $redis = Redis::connection('default');
            
            // 使用 Hash 存储分钟级聚合
            $redis->hIncrBy($key, 'total', 1);
            $redis->hIncrByFloat($key, 'duration_sum', $durationMs);
            
            if ($statusCode >= 400) {
                $redis->hIncrBy($key, 'errors', 1);
            }
            
            // 按路径统计
            $pathKey = self::PREFIX . "api_path:{$minute}:{$path}";
            $redis->hIncrBy($pathKey, 'count', 1);
            $redis->hIncrByFloat($pathKey, 'duration', $durationMs);
            
            // 设置过期时间
            $redis->expire($key, self::TTL);
            $redis->expire($pathKey, self::TTL);
        } catch (\Throwable $e) {
            // 指标收集失败不影响业务
        }
    }
    
    /**
     * 记录 SSE 连接指标
     */
    public static function recordSSE(string $action, int $userId = 0): void
    {
        $minute = date('YmdHi');
        $key = self::PREFIX . "sse:{$minute}";
        
        try {
            $redis = Redis::connection('default');
            
            if ($action === 'connect') {
                $redis->hIncrBy($key, 'connects', 1);
                // 当前活跃连接数
                $redis->incr(self::PREFIX . 'sse:active');
            } elseif ($action === 'disconnect') {
                $redis->hIncrBy($key, 'disconnects', 1);
                $redis->decr(self::PREFIX . 'sse:active');
            } elseif ($action === 'message') {
                $redis->hIncrBy($key, 'messages', 1);
            } elseif ($action === 'error') {
                $redis->hIncrBy($key, 'errors', 1);
            }
            
            $redis->expire($key, self::TTL);
        } catch (\Throwable $e) {
            // 指标收集失败不影响业务
        }
    }
    
    /**
     * 记录队列指标
     */
    public static function recordQueue(string $queue, string $action, float $durationMs = 0): void
    {
        $minute = date('YmdHi');
        $key = self::PREFIX . "queue:{$queue}:{$minute}";
        
        try {
            $redis = Redis::connection('default');
            
            if ($action === 'push') {
                $redis->hIncrBy($key, 'pushed', 1);
            } elseif ($action === 'process') {
                $redis->hIncrBy($key, 'processed', 1);
                $redis->hIncrByFloat($key, 'duration_sum', $durationMs);
            } elseif ($action === 'fail') {
                $redis->hIncrBy($key, 'failed', 1);
            }
            
            $redis->expire($key, self::TTL);
        } catch (\Throwable $e) {
            // 指标收集失败不影响业务
        }
    }
    
    /**
     * 获取 API 指标摘要
     */
    public static function getApiSummary(int $minutes = 5): array
    {
        $summary = [
            'total_requests' => 0,
            'total_errors' => 0,
            'avg_duration_ms' => 0,
            'error_rate' => 0,
            'qps' => 0,
        ];
        
        try {
            $redis = Redis::connection('default');
            $totalDuration = 0;
            
            for ($i = 0; $i < $minutes; $i++) {
                $minute = date('YmdHi', strtotime("-{$i} minutes"));
                $key = self::PREFIX . "api:{$minute}";
                
                $data = $redis->hGetAll($key);
                if ($data) {
                    $summary['total_requests'] += (int)($data['total'] ?? 0);
                    $summary['total_errors'] += (int)($data['errors'] ?? 0);
                    $totalDuration += (float)($data['duration_sum'] ?? 0);
                }
            }
            
            if ($summary['total_requests'] > 0) {
                $summary['avg_duration_ms'] = round($totalDuration / $summary['total_requests'], 2);
                $summary['error_rate'] = round($summary['total_errors'] / $summary['total_requests'] * 100, 2);
                $summary['qps'] = round($summary['total_requests'] / ($minutes * 60), 2);
            }
        } catch (\Throwable $e) {
            // 获取失败返回空摘要
        }
        
        return $summary;
    }
    
    /**
     * 获取 SSE 指标摘要
     */
    public static function getSSESummary(): array
    {
        $summary = [
            'active_connections' => 0,
            'recent_connects' => 0,
            'recent_messages' => 0,
            'recent_errors' => 0,
        ];
        
        try {
            $redis = Redis::connection('default');
            
            $summary['active_connections'] = (int)$redis->get(self::PREFIX . 'sse:active') ?: 0;
            
            // 最近5分钟
            for ($i = 0; $i < 5; $i++) {
                $minute = date('YmdHi', strtotime("-{$i} minutes"));
                $key = self::PREFIX . "sse:{$minute}";
                
                $data = $redis->hGetAll($key);
                if ($data) {
                    $summary['recent_connects'] += (int)($data['connects'] ?? 0);
                    $summary['recent_messages'] += (int)($data['messages'] ?? 0);
                    $summary['recent_errors'] += (int)($data['errors'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            // 获取失败返回空摘要
        }
        
        return $summary;
    }
    
    /**
     * 获取所有指标（用于 /metrics 路由导出）
     */
    public static function export(): array
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'api' => self::getApiSummary(),
            'sse' => self::getSSESummary(),
        ];
    }
    
    /**
     * 输出 Prometheus 格式指标
     */
    public static function exportPrometheus(): string
    {
        $api = self::getApiSummary();
        $sse = self::getSSESummary();
        
        $lines = [
            "# HELP api_requests_total Total API requests in last 5 minutes",
            "# TYPE api_requests_total counter",
            "api_requests_total {$api['total_requests']}",
            "",
            "# HELP api_errors_total Total API errors in last 5 minutes",
            "# TYPE api_errors_total counter",
            "api_errors_total {$api['total_errors']}",
            "",
            "# HELP api_duration_avg_ms Average API response time in ms",
            "# TYPE api_duration_avg_ms gauge",
            "api_duration_avg_ms {$api['avg_duration_ms']}",
            "",
            "# HELP api_qps Requests per second",
            "# TYPE api_qps gauge",
            "api_qps {$api['qps']}",
            "",
            "# HELP sse_active_connections Current active SSE connections",
            "# TYPE sse_active_connections gauge",
            "sse_active_connections {$sse['active_connections']}",
            "",
            "# HELP sse_messages_total SSE messages sent in last 5 minutes",
            "# TYPE sse_messages_total counter",
            "sse_messages_total {$sse['recent_messages']}",
        ];
        
        return implode("\n", $lines);
    }
}

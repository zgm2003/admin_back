<?php

namespace app\lib\Ai;

use app\enum\AiEnum;
use GuzzleHttp\Client;
use support\Db;
use support\Log;

/**
 * 工具执行器
 * 根据 executor_type 分发执行，统一返回 string 结果
 * 异常不上抛，捕获后返回错误描述字符串
 */
class ToolExecutor
{
    /** 内置工具注册表 */
    private static array $internalTools = [
        'get_current_time' => [self::class, 'toolGetCurrentTime'],
    ];

    /**
     * 执行工具
     */
    public static function execute(object $toolRecord, array $inputs): string
    {
        try {
            return match ((int)$toolRecord->executor_type) {
                AiEnum::EXECUTOR_INTERNAL       => self::executeInternal($toolRecord->code, $inputs),
                AiEnum::EXECUTOR_HTTP_WHITELIST  => self::executeHttp($toolRecord->executor_config ?? [], $inputs),
                AiEnum::EXECUTOR_SQL_READONLY    => self::executeSql($toolRecord->executor_config ?? [], $inputs),
                default => "未知的执行器类型: {$toolRecord->executor_type}",
            };
        } catch (\Throwable $e) {
            Log::warning("[ToolExecutor] 执行失败: {$toolRecord->code}, {$e->getMessage()}");
            return "工具执行失败: {$e->getMessage()}";
        }
    }

    // ==================== Internal 执行器 ====================

    private static function executeInternal(string $code, array $inputs): string
    {
        if (!isset(self::$internalTools[$code])) {
            return "未注册的内置工具: {$code}";
        }
        $result = call_user_func(self::$internalTools[$code], $inputs);
        return is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private static function toolGetCurrentTime(array $inputs): string
    {
        $format = $inputs['format'] ?? 'Y-m-d H:i:s';
        return date($format);
    }

    // ==================== HTTP 白名单执行器 ====================

    private static function executeHttp(array $config, array $inputs): string
    {
        $url = $config['url'] ?? '';
        if (!str_starts_with($url, 'https://')) {
            return '仅允许 HTTPS 请求';
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            return 'URL 解析失败';
        }

        // 拒绝 IP 直连
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return '不允许 IP 直连，请使用域名';
        }

        // DNS 解析后校验 IP
        $ip = gethostbyname($host);
        if ($ip === $host) {
            return "DNS 解析失败: {$host}";
        }
        if (self::isInternalAddress($ip)) {
            return '目标地址为内网地址，禁止访问';
        }

        $method = strtoupper($config['method'] ?? 'POST');
        $client = new Client([
            'timeout'          => 30,
            'allow_redirects'  => false,
            'verify'           => true,
        ]);

        $options = ['headers' => ['Content-Type' => 'application/json']];
        if ($method === 'GET') {
            $options['query'] = $inputs;
        } else {
            $options['json'] = $inputs;
        }

        $response = $client->request($method, $url, $options);
        $body = $response->getBody()->getContents();

        // 截断至 10KB
        if (strlen($body) > 10240) {
            $body = substr($body, 0, 10240) . '...[截断]';
        }

        return $body;
    }

    /**
     * SSRF 防护：校验 IP 是否为内网地址
     */
    public static function isInternalAddress(string $ip): bool
    {
        // IPv6 回环 + ULA
        if ($ip === '::1' || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
            return true;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        $ranges = [
            ['127.0.0.0', '127.255.255.255'],     // 127.0.0.0/8
            ['10.0.0.0', '10.255.255.255'],        // 10.0.0.0/8
            ['172.16.0.0', '172.31.255.255'],       // 172.16.0.0/12
            ['192.168.0.0', '192.168.255.255'],     // 192.168.0.0/16
            ['169.254.0.0', '169.254.255.255'],     // 169.254.0.0/16 link-local
            ['0.0.0.0', '0.255.255.255'],           // 0.0.0.0/8
        ];

        foreach ($ranges as [$start, $end]) {
            if ($long >= ip2long($start) && $long <= ip2long($end)) {
                return true;
            }
        }

        return false;
    }

    // ==================== SQL 只读执行器 ====================

    private static function executeSql(array $config, array $inputs): string
    {
        $sql = trim($config['sql'] ?? '');
        if (empty($sql) || !preg_match('/^\s*SELECT\b/i', $sql)) {
            return '仅允许 SELECT 查询';
        }

        // 拒绝写操作关键字
        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|REPLACE|GRANT|REVOKE)\b/i', $sql)) {
            return '检测到写操作关键字，拒绝执行';
        }

        // 自动追加 LIMIT 100（如果没有）
        if (!preg_match('/\bLIMIT\b/i', $sql)) {
            $sql = rtrim($sql, "; \t\n\r") . ' LIMIT 100';
        }

        // 参数绑定：将 :param_name 替换为 PDO 占位符
        $bindings = [];
        $boundSql = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function ($matches) use ($inputs, &$bindings) {
            $key = $matches[1];
            $bindings[] = $inputs[$key] ?? '';
            return '?';
        }, $sql);

        $rows = Db::select($boundSql, $bindings);
        return json_encode($rows, JSON_UNESCAPED_UNICODE);
    }
}

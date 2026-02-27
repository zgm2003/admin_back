<?php

namespace tests\Unit;

use app\lib\Ai\ToolExecutor;
use app\enum\AiEnum;
use PHPUnit\Framework\TestCase;

class ToolExecutorTest extends TestCase
{
    // ==================== Internal 执行器 ====================

    public function testInternalGetCurrentTime(): void
    {
        $record = (object)[
            'code' => 'get_current_time',
            'executor_type' => AiEnum::EXECUTOR_INTERNAL,
            'executor_config' => [],
        ];
        $result = ToolExecutor::execute($record, []);
        // 应返回当前时间字符串
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    public function testInternalUnregisteredCodeReturnsError(): void
    {
        $record = (object)[
            'code' => 'nonexistent_tool',
            'executor_type' => AiEnum::EXECUTOR_INTERNAL,
            'executor_config' => [],
        ];
        $result = ToolExecutor::execute($record, []);
        $this->assertStringContainsString('未注册的内置工具', $result);
    }

    // ==================== SSRF 防护 ====================

    public function testIsInternalAddress127(): void
    {
        $this->assertTrue(ToolExecutor::isInternalAddress('127.0.0.1'));
        $this->assertTrue(ToolExecutor::isInternalAddress('127.255.255.255'));
    }

    public function testIsInternalAddress10(): void
    {
        $this->assertTrue(ToolExecutor::isInternalAddress('10.0.0.1'));
        $this->assertTrue(ToolExecutor::isInternalAddress('10.255.255.255'));
    }

    public function testIsInternalAddress172(): void
    {
        $this->assertTrue(ToolExecutor::isInternalAddress('172.16.0.1'));
        $this->assertTrue(ToolExecutor::isInternalAddress('172.31.255.255'));
        $this->assertFalse(ToolExecutor::isInternalAddress('172.32.0.1'));
    }

    public function testIsInternalAddress192(): void
    {
        $this->assertTrue(ToolExecutor::isInternalAddress('192.168.0.1'));
        $this->assertTrue(ToolExecutor::isInternalAddress('192.168.255.255'));
    }

    public function testIsInternalAddressLinkLocal(): void
    {
        $this->assertTrue(ToolExecutor::isInternalAddress('169.254.0.1'));
        $this->assertTrue(ToolExecutor::isInternalAddress('169.254.255.255'));
    }

    public function testIsInternalAddressZeroNet(): void
    {
        $this->assertTrue(ToolExecutor::isInternalAddress('0.0.0.0'));
        $this->assertTrue(ToolExecutor::isInternalAddress('0.255.255.255'));
    }

    public function testIsInternalAddressIPv6Loopback(): void
    {
        $this->assertTrue(ToolExecutor::isInternalAddress('::1'));
    }

    public function testIsInternalAddressIPv6ULA(): void
    {
        $this->assertTrue(ToolExecutor::isInternalAddress('fc00::1'));
        $this->assertTrue(ToolExecutor::isInternalAddress('fd12:3456::1'));
    }

    public function testPublicAddressNotInternal(): void
    {
        $this->assertFalse(ToolExecutor::isInternalAddress('8.8.8.8'));
        $this->assertFalse(ToolExecutor::isInternalAddress('1.1.1.1'));
        $this->assertFalse(ToolExecutor::isInternalAddress('203.0.113.1'));
    }

    // ==================== HTTP 执行器 ====================

    public function testHttpRejectsNonHttps(): void
    {
        $record = (object)[
            'code' => 'test_http',
            'executor_type' => AiEnum::EXECUTOR_HTTP_WHITELIST,
            'executor_config' => ['url' => 'http://example.com/api'],
        ];
        $result = ToolExecutor::execute($record, []);
        $this->assertStringContainsString('HTTPS', $result);
    }

    public function testHttpRejectsIpDirect(): void
    {
        $record = (object)[
            'code' => 'test_http',
            'executor_type' => AiEnum::EXECUTOR_HTTP_WHITELIST,
            'executor_config' => ['url' => 'https://127.0.0.1/api'],
        ];
        $result = ToolExecutor::execute($record, []);
        $this->assertStringContainsString('IP', $result);
    }

    // ==================== SQL 执行器 ====================

    public function testSqlRejectsInsert(): void
    {
        $record = (object)[
            'code' => 'test_sql',
            'executor_type' => AiEnum::EXECUTOR_SQL_READONLY,
            'executor_config' => ['sql' => 'INSERT INTO users (name) VALUES (:name)'],
        ];
        $result = ToolExecutor::execute($record, ['name' => 'test']);
        $this->assertStringContainsString('SELECT', $result);
    }

    public function testSqlRejectsDrop(): void
    {
        $record = (object)[
            'code' => 'test_sql',
            'executor_type' => AiEnum::EXECUTOR_SQL_READONLY,
            'executor_config' => ['sql' => 'DROP TABLE users'],
        ];
        $result = ToolExecutor::execute($record, []);
        $this->assertStringContainsString('SELECT', $result);
    }

    public function testSqlRejectsSelectWithWriteKeyword(): void
    {
        $record = (object)[
            'code' => 'test_sql',
            'executor_type' => AiEnum::EXECUTOR_SQL_READONLY,
            'executor_config' => ['sql' => 'SELECT 1; DROP TABLE users'],
        ];
        $result = ToolExecutor::execute($record, []);
        $this->assertStringContainsString('写操作', $result);
    }

    public function testUnknownExecutorType(): void
    {
        $record = (object)[
            'code' => 'test',
            'executor_type' => 99,
            'executor_config' => [],
        ];
        $result = ToolExecutor::execute($record, []);
        $this->assertStringContainsString('未知的执行器类型', $result);
    }
}

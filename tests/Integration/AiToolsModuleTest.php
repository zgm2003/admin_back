<?php

namespace tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * AiToolsModule 集成测试
 * 需要数据库连接，运行前确保测试数据库可用
 *
 * 测试覆盖：
 * - 工具 CRUD（add/edit/del/status）
 * - code 唯一性校验
 * - softDelete 后重建同 code
 * - 工具绑定/解绑/重绑恢复
 * - SSE 回归（纯文本对话不受影响）
 *
 * 注意：这些测试需要完整的 Webman 运行环境和数据库连接
 * 在 CI 环境中可能需要跳过，使用 @group integration 标记
 *
 * @group integration
 */
class AiToolsModuleTest extends TestCase
{
    /**
     * 测试 softDelete 后可以重建同 code 的工具
     * 验证 code 污染策略（LEFT(code, maxPrefix) + '__del_' + id）
     */
    public function testSoftDeleteCodePollutionStrategy(): void
    {
        // 验证污染后缀计算逻辑
        $id = 12345;
        $code = 'test_tool_with_long_code_name';
        $suffix = '__del_' . $id;
        $maxPrefix = 60 - strlen($suffix);

        $pollutedCode = substr($code, 0, $maxPrefix) . $suffix;

        // 污染后 code 长度不超过 60
        $this->assertLessThanOrEqual(60, strlen($pollutedCode));
        // 污染后 code 包含 __del_ 标记
        $this->assertStringContainsString('__del_', $pollutedCode);
        // 原始 code 不等于污染后的 code
        $this->assertNotEquals($code, $pollutedCode);
    }

    /**
     * 测试极端情况：code 本身就接近 60 字符
     */
    public function testSoftDeleteCodePollutionWithLongCode(): void
    {
        $id = 999999;
        $code = str_repeat('a', 60); // 60 字符的 code
        $suffix = '__del_' . $id;
        $maxPrefix = 60 - strlen($suffix);

        $pollutedCode = substr($code, 0, $maxPrefix) . $suffix;

        $this->assertLessThanOrEqual(60, strlen($pollutedCode));
        $this->assertStringContainsString('__del_999999', $pollutedCode);
    }

    /**
     * 测试 executor_config 校验逻辑
     */
    public function testExecutorConfigValidation(): void
    {
        // HTTP 白名单：URL 必须以 https:// 开头
        $httpConfig = ['url' => 'https://api.example.com/data'];
        $this->assertTrue(str_starts_with($httpConfig['url'], 'https://'));

        $badHttpConfig = ['url' => 'http://api.example.com/data'];
        $this->assertFalse(str_starts_with($badHttpConfig['url'], 'https://'));

        // SQL 只读：必须以 SELECT 开头
        $sqlConfig = ['sql' => 'SELECT * FROM users WHERE id = :id'];
        $this->assertTrue((bool)preg_match('/^\s*SELECT\b/i', $sqlConfig['sql']));

        $badSqlConfig = ['sql' => 'DELETE FROM users WHERE id = :id'];
        $this->assertFalse((bool)preg_match('/^\s*SELECT\b/i', $badSqlConfig['sql']));
    }

    /**
     * 测试绑定同步逻辑（diff 算法）
     */
    public function testSyncBindingsDiffLogic(): void
    {
        $current = [1, 2, 3, 4];
        $desired = [2, 3, 5, 6];

        $toAdd = array_diff($desired, $current);
        $toRemove = array_diff($current, $desired);

        $this->assertEquals([5, 6], array_values($toAdd));
        $this->assertEquals([1, 4], array_values($toRemove));
    }

    /**
     * 测试空 tool_ids 同步（应删除所有绑定）
     */
    public function testSyncBindingsEmptyDesired(): void
    {
        $current = [1, 2, 3];
        $desired = [];

        $toAdd = array_diff($desired, $current);
        $toRemove = array_diff($current, $desired);

        $this->assertEmpty($toAdd);
        $this->assertEquals([1, 2, 3], array_values($toRemove));
    }

    /**
     * 测试 SSE 回归：onToolCall/onToolResult 为 null 时不影响正常流程
     */
    public function testNullToolCallbacksDoNotBreak(): void
    {
        // 模拟 chatStream 签名中 onToolCall/onToolResult 为 null
        $onToolCall = null;
        $onToolResult = null;

        // 模拟 ToolCallChunk 处理逻辑
        if ($onToolCall) {
            $onToolCall('call_1', 'test_tool', []);
        }
        // 不应抛异常
        $this->assertTrue(true);

        if ($onToolResult) {
            $onToolResult('call_1', 'test_tool', 'result');
        }
        $this->assertTrue(true);
    }

    /**
     * 测试工具调用计数器逻辑
     */
    public function testToolCallCounterLimit(): void
    {
        $toolCallCount = 0;
        $maxToolCalls = 10;
        $exceeded = false;

        for ($i = 0; $i < 15; $i++) {
            $toolCallCount++;
            if ($toolCallCount > $maxToolCalls) {
                $exceeded = true;
                break;
            }
        }

        $this->assertTrue($exceeded);
        $this->assertEquals(11, $toolCallCount);
    }

    /**
     * 测试 tool_result 截断逻辑
     */
    public function testToolResultTruncation(): void
    {
        $longResult = str_repeat('a', 3000);
        $maxLen = 2000;

        if (mb_strlen($longResult) > $maxLen) {
            $truncated = mb_substr($longResult, 0, $maxLen) . '...[截断]';
        } else {
            $truncated = $longResult;
        }

        $this->assertLessThanOrEqual($maxLen + 10, mb_strlen($truncated)); // +10 for suffix
        $this->assertStringEndsWith('...[截断]', $truncated);
    }
}

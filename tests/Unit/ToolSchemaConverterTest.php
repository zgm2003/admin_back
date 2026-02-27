<?php

namespace tests\Unit;

use app\lib\Ai\ToolSchemaConverter;
use NeuronAI\Tools\PropertyType;
use PHPUnit\Framework\TestCase;

class ToolSchemaConverterTest extends TestCase
{
    // ==================== parseProperties ====================

    public function testParseFormatA(): void
    {
        $schema = [
            'properties' => [
                'keyword' => ['type' => 'string', 'description' => '搜索关键词', 'required' => true],
                'limit'   => ['type' => 'integer', 'description' => '返回数量'],
            ],
        ];
        $props = ToolSchemaConverter::parseProperties($schema);

        $this->assertCount(2, $props);
        $this->assertEquals('keyword', $props[0]->getName());
        $this->assertEquals(PropertyType::STRING, $props[0]->getType());
        $this->assertTrue($props[0]->isRequired());
        $this->assertEquals('limit', $props[1]->getName());
        $this->assertEquals(PropertyType::INTEGER, $props[1]->getType());
        $this->assertFalse($props[1]->isRequired());
    }

    public function testParseFormatB(): void
    {
        $schema = [
            'properties' => [
                'keyword' => ['type' => 'string', 'description' => '搜索关键词'],
                'limit'   => ['type' => 'integer', 'description' => '返回数量'],
            ],
            'required' => ['keyword'],
        ];
        $props = ToolSchemaConverter::parseProperties($schema);

        $this->assertCount(2, $props);
        $this->assertTrue($props[0]->isRequired());
        $this->assertFalse($props[1]->isRequired());
    }

    public function testParseFormatABUnion(): void
    {
        // 格式 A + B 并集：keyword 在 A 中 required=true，limit 在 B 的 required 数组中
        $schema = [
            'properties' => [
                'keyword' => ['type' => 'string', 'description' => '搜索关键词', 'required' => true],
                'limit'   => ['type' => 'integer', 'description' => '返回数量'],
            ],
            'required' => ['limit'],
        ];
        $props = ToolSchemaConverter::parseProperties($schema);

        $this->assertTrue($props[0]->isRequired());  // keyword: format A
        $this->assertTrue($props[1]->isRequired());   // limit: format B
    }

    public function testParseEmptySchema(): void
    {
        $this->assertEmpty(ToolSchemaConverter::parseProperties(null));
        $this->assertEmpty(ToolSchemaConverter::parseProperties([]));
        $this->assertEmpty(ToolSchemaConverter::parseProperties(['properties' => []]));
    }

    public function testParseInvalidTypeFallsBackToString(): void
    {
        $schema = [
            'properties' => [
                'field' => ['type' => 'unknown_type', 'description' => 'test'],
            ],
        ];
        $props = ToolSchemaConverter::parseProperties($schema);

        $this->assertCount(1, $props);
        $this->assertEquals(PropertyType::STRING, $props[0]->getType());
    }

    public function testParseAllTypes(): void
    {
        $schema = [
            'properties' => [
                'a' => ['type' => 'string'],
                'b' => ['type' => 'integer'],
                'c' => ['type' => 'number'],
                'd' => ['type' => 'boolean'],
                'e' => ['type' => 'array'],
                'f' => ['type' => 'object'],
                'g' => ['type' => 'int'],
                'h' => ['type' => 'float'],
                'i' => ['type' => 'bool'],
            ],
        ];
        $props = ToolSchemaConverter::parseProperties($schema);

        $this->assertEquals(PropertyType::STRING, $props[0]->getType());
        $this->assertEquals(PropertyType::INTEGER, $props[1]->getType());
        $this->assertEquals(PropertyType::NUMBER, $props[2]->getType());
        $this->assertEquals(PropertyType::BOOLEAN, $props[3]->getType());
        $this->assertEquals(PropertyType::ARRAY, $props[4]->getType());
        $this->assertEquals(PropertyType::OBJECT, $props[5]->getType());
        $this->assertEquals(PropertyType::INTEGER, $props[6]->getType());  // int alias
        $this->assertEquals(PropertyType::NUMBER, $props[7]->getType());   // float alias
        $this->assertEquals(PropertyType::BOOLEAN, $props[8]->getType());  // bool alias
    }

    // ==================== toNeuronTool ====================

    public function testToNeuronToolBasic(): void
    {
        $record = (object)[
            'code' => 'get_current_time',
            'name' => '获取当前时间',
            'description' => '返回当前服务器时间',
            'schema_json' => [
                'properties' => [
                    'format' => ['type' => 'string', 'description' => '时间格式'],
                ],
            ],
            'executor_type' => 1,
            'executor_config' => [],
        ];

        $tool = ToolSchemaConverter::toNeuronTool($record);

        $this->assertEquals('get_current_time', $tool->getName());
        $this->assertEquals('返回当前服务器时间', $tool->getDescription());
    }

    public function testToNeuronToolDescriptionFallback(): void
    {
        $record = (object)[
            'code' => 'test_tool',
            'name' => '测试工具',
            'description' => null,
            'schema_json' => null,
            'executor_type' => 1,
            'executor_config' => [],
        ];

        $tool = ToolSchemaConverter::toNeuronTool($record);

        // description 为 null 时回退到 name
        $this->assertEquals('测试工具', $tool->getDescription());
    }

    public function testToNeuronToolSchemaJsonAsString(): void
    {
        $record = (object)[
            'code' => 'test_tool',
            'name' => '测试工具',
            'description' => 'desc',
            'schema_json' => '{"properties":{"q":{"type":"string","description":"query"}}}',
            'executor_type' => 1,
            'executor_config' => [],
        ];

        $tool = ToolSchemaConverter::toNeuronTool($record);
        $this->assertEquals('test_tool', $tool->getName());
    }
}

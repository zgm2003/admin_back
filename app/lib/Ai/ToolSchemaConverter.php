<?php

namespace app\lib\Ai;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

/**
 * 将 ai_tools 数据库记录转换为 Neuron AI Tool 对象
 */
class ToolSchemaConverter
{
    /**
     * 将一条 ai_tools 记录转换为 Neuron Tool
     */
    public static function toNeuronTool(object $toolRecord): Tool
    {
        $properties = self::parseProperties(
            is_string($toolRecord->schema_json)
                ? json_decode($toolRecord->schema_json, true)
                : ($toolRecord->schema_json ?? null)
        );

        $tool = Tool::make(
            name: $toolRecord->code,
            description: $toolRecord->description ?? $toolRecord->name,
            properties: $properties,
        );

        // Neuron 在执行工具时会 clone Tool，再把输入参数写到 clone 上。
        // 这里不能再读取外层 $tool->getInputs()，否则会拿到空参数。
        $propertyNames = array_map(fn(ToolProperty $p) => $p->getName(), $properties);
        $tool->setCallable(function (mixed ...$args) use ($toolRecord, $propertyNames) {
            $inputs = [];
            foreach ($args as $key => $value) {
                if (is_string($key)) {
                    // PHP 8 下，展开字符串键数组会作为命名参数传入
                    $inputs[$key] = $value;
                    continue;
                }

                if (isset($propertyNames[$key])) {
                    $inputs[$propertyNames[$key]] = $value;
                }
            }

            return ToolExecutor::execute($toolRecord, $inputs);
        });

        return $tool;
    }

    /**
     * 解析 schema_json 为 ToolProperty 数组
     * 支持格式 A（属性内 required:true）和格式 B（顶层 required:[] 数组），取并集
     */
    public static function parseProperties(?array $schemaJson): array
    {
        if (empty($schemaJson) || empty($schemaJson['properties'])) {
            return [];
        }

        $props = $schemaJson['properties'];
        // 格式 B 的顶层 required 数组
        $topRequired = array_flip($schemaJson['required'] ?? []);

        $result = [];
        foreach ($props as $name => $def) {
            $type = self::mapPropertyType($def['type'] ?? 'string');
            $desc = $def['description'] ?? '';
            // 格式 A: 属性内 required:true；格式 B: 顶层 required 数组；取并集
            $required = !empty($def['required']) || isset($topRequired[$name]);

            $result[] = new ToolProperty(
                name: $name,
                type: $type,
                description: $desc,
                required: $required,
            );
        }

        return $result;
    }

    /**
     * JSON Schema type → Neuron PropertyType 枚举
     */
    private static function mapPropertyType(string $type): PropertyType
    {
        return match (strtolower($type)) {
            'string'          => PropertyType::STRING,
            'integer', 'int'  => PropertyType::INTEGER,
            'number', 'float' => PropertyType::NUMBER,
            'boolean', 'bool' => PropertyType::BOOLEAN,
            'array'           => PropertyType::ARRAY,
            'object'          => PropertyType::OBJECT,
            default           => PropertyType::STRING,
        };
    }
}

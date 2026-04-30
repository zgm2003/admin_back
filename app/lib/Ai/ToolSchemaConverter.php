<?php

namespace app\lib\Ai;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\ObjectProperty;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\ToolPropertyInterface;
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
        $propertyNames = array_map(fn(ToolPropertyInterface $p) => $p->getName(), $properties);
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
        $topRequired = array_flip(
            isset($schemaJson['required']) && \is_array($schemaJson['required'])
                ? $schemaJson['required']
                : []
        );

        $result = [];
        foreach ($props as $name => $def) {
            // 格式 A: 属性内 required:true；格式 B: 顶层 required 数组；取并集
            $required = self::isRequiredFlag($def['required'] ?? null) || isset($topRequired[$name]);
            $result[] = self::createProperty($name, $def, $required);
        }

        return $result;
    }

    private static function createProperty(string $name, array $def, bool $required): ToolPropertyInterface
    {
        $type = strtolower((string)($def['type'] ?? 'string'));
        $desc = $def['description'] ?? '';

        if ($type === 'array') {
            $items = null;
            if (isset($def['items']) && \is_array($def['items'])) {
                $items = self::createProperty($name . '_item', $def['items'], false);
            }

            return new ArrayProperty(
                name: $name,
                description: $desc,
                required: $required,
                items: $items,
                minItems: self::nullableInt($def['minItems'] ?? null),
                maxItems: self::nullableInt($def['maxItems'] ?? null),
            );
        }

        if ($type === 'object') {
            $nestedRequired = array_flip(
                isset($def['required']) && \is_array($def['required'])
                    ? $def['required']
                    : []
            );
            $properties = [];
            foreach (($def['properties'] ?? []) as $childName => $childDef) {
                if (!\is_array($childDef)) {
                    continue;
                }

                $properties[] = self::createProperty(
                    (string)$childName,
                    $childDef,
                    self::isRequiredFlag($childDef['required'] ?? null) || isset($nestedRequired[$childName])
                );
            }

            return new ObjectProperty(
                name: $name,
                description: $desc,
                required: $required,
                properties: $properties,
            );
        }

        return new ToolProperty(
            name: $name,
            type: self::mapPropertyType($type),
            description: $desc,
            required: $required,
            enum: \is_array($def['enum'] ?? null) ? $def['enum'] : [],
        );
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private static function isRequiredFlag(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value === 1;
        }

        if (\is_string($value)) {
            return strtolower($value) === 'true' || $value === '1';
        }

        return false;
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

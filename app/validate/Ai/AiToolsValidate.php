<?php

namespace app\validate\Ai;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;
use app\enum\AiEnum;

class AiToolsValidate
{
    public static function add(): array
    {
        return [
            'name'            => v::stringType()->length(1, 50)->setName('工具名称'),
            'code'            => v::regex('/^[a-z][a-z0-9_]{0,59}$/')->setName('工具编码'),
            'description'     => v::optional(v::stringType()->length(0, 255))->setName('描述'),
            'schema_json'     => v::optional(v::arrayType())->setName('参数Schema'),
            'executor_type'   => v::intVal()->in([AiEnum::EXECUTOR_INTERNAL, AiEnum::EXECUTOR_HTTP_WHITELIST, AiEnum::EXECUTOR_SQL_READONLY])->setName('执行器类型'),
            'executor_config' => v::optional(v::arrayType())->setName('执行器配置'),
            'status'          => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'              => v::intVal()->positive()->setName('ID'),
            'name'            => v::optional(v::stringType()->length(1, 50))->setName('工具名称'),
            'code'            => v::optional(v::regex('/^[a-z][a-z0-9_]{0,59}$/'))->setName('工具编码'),
            'description'     => v::optional(v::stringType()->length(0, 255))->setName('描述'),
            'schema_json'     => v::optional(v::arrayType())->setName('参数Schema'),
            'executor_type'   => v::optional(v::intVal()->in([AiEnum::EXECUTOR_INTERNAL, AiEnum::EXECUTOR_HTTP_WHITELIST, AiEnum::EXECUTOR_SQL_READONLY]))->setName('执行器类型'),
            'executor_config' => v::optional(v::arrayType())->setName('执行器配置'),
            'status'          => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'     => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page'  => v::optional(v::intVal()->positive()),
            'name'          => v::optional(v::stringType()),
            'status'        => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr))),
            'executor_type' => v::optional(v::intVal()->in([AiEnum::EXECUTOR_INTERNAL, AiEnum::EXECUTOR_HTTP_WHITELIST, AiEnum::EXECUTOR_SQL_READONLY])),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
        ];
    }

    public static function status(): array
    {
        return [
            'id'     => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
            'status' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('状态'),
        ];
    }

    public static function bindTools(): array
    {
        return [
            'agent_id' => v::intVal()->positive()->setName('智能体ID'),
            'tool_ids' => v::arrayType()->setName('工具ID列表'),
        ];
    }

    public static function getAgentTools(): array
    {
        return [
            'agent_id' => v::intVal()->positive()->setName('智能体ID'),
        ];
    }
}

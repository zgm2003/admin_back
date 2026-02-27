<?php

namespace app\validate\Ai;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class AiModelsValidate
{
    /**
     * 创建验证规则
     */
    public static function add(): array
    {
        return [
            'name'           => v::stringType()->length(1, 50)->setName('模型名称'),
            'driver'         => v::stringType()->length(1, 30)->setName('驱动'),
            'model_code'     => v::stringType()->length(1, 80)->setName('模型标识'),
            'endpoint'       => v::optional(v::stringType()->length(0, 255))->setName('接口地址'),
            'api_key'        => v::optional(v::stringType())->setName('API Key'),
            'modalities'     => v::optional(v::arrayType())->setName('多模态能力'),
            'status'         => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
        ];
    }

    /**
     * 更新验证规则
     */
    public static function edit(): array
    {
        return [
            'id'             => v::intVal()->positive()->setName('ID'),
            'name'           => v::optional(v::stringType()->length(1, 50))->setName('模型名称'),
            'driver'         => v::optional(v::stringType()->length(1, 30))->setName('驱动'),
            'model_code'     => v::optional(v::stringType()->length(1, 80))->setName('模型标识'),
            'endpoint'       => v::optional(v::stringType()->length(0, 255))->setName('接口地址'),
            'api_key'        => v::optional(v::stringType())->setName('API Key'),
            'modalities'     => v::optional(v::arrayType())->setName('多模态能力'),
            'status'         => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
        ];
    }

    /**
     * 删除验证规则
     */
    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
        ];
    }

    /**
     * 列表验证规则
     */
    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page' => v::optional(v::intVal()->positive()),
            'driver'       => v::optional(v::stringType()),
            'status'       => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr))),
            'name'         => v::optional(v::stringType()),
        ];
    }

    /**
     * 状态变更验证规则
     */
    public static function setStatus(): array
    {
        return [
            'id'     => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
            'status' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('状态'),
        ];
    }
}

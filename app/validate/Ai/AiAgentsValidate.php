<?php

namespace app\validate\Ai;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;
use app\enum\AiEnum;

class AiAgentsValidate
{
    public static function add(): array
    {
        return [
            'name'         => v::stringType()->length(1, 50)->setName('智能体名称'),
            'model_id'     => v::intVal()->positive()->setName('模型ID'),
            'avatar'       => v::optional(v::stringType()->length(0, 255))->setName('头像'),
            'system_prompt'=> v::optional(v::stringType())->setName('系统提示词'),
            'mode'         => v::optional(v::stringType()->in(array_keys(AiEnum::$modeArr)))->setName('模式'),
            'scene'        => v::optional(v::stringType()->in(array_keys(AiEnum::$sceneArr)))->setName('场景'),
            'status'       => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
            'tool_ids'     => v::optional(v::arrayType()->each(v::intVal()->positive()))->setName('工具ID列表'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'           => v::intVal()->positive()->setName('ID'),
            'name'         => v::optional(v::stringType()->length(1, 50))->setName('智能体名称'),
            'model_id'     => v::optional(v::intVal()->positive())->setName('模型ID'),
            'avatar'       => v::optional(v::stringType()->length(0, 255))->setName('头像'),
            'system_prompt'=> v::optional(v::stringType())->setName('系统提示词'),
            'mode'         => v::optional(v::stringType()->in(array_keys(AiEnum::$modeArr)))->setName('模式'),
            'scene'        => v::optional(v::stringType()->in(array_keys(AiEnum::$sceneArr)))->setName('场景'),
            'status'       => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
            'tool_ids'     => v::optional(v::arrayType()->each(v::intVal()->positive()))->setName('工具ID列表'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page' => v::optional(v::intVal()->positive()),
            'model_id'     => v::optional(v::intVal()->positive()),
            'status'       => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr))),
            'mode'         => v::optional(v::stringType()->in(array_keys(AiEnum::$modeArr))),
            'name'         => v::optional(v::stringType()),
        ];
    } 

    public static function status(): array
    {
        return [
            'id'     => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
            'status' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('状态'),
        ];
    }
}

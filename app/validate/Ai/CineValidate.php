<?php

namespace app\validate\Ai;

use app\enum\CineEnum;
use app\enum\CommonEnum;
use Respect\Validation\Validator as v;

class CineValidate
{
    public static function add(): array
    {
        return [
            'title' => v::stringType()->length(1, 120)->setName('项目标题'),
            'source_text' => v::stringType()->length(1, 50000)->setName('原始素材'),
            'style' => v::optional(v::stringType()->length(0, 255))->setName('风格'),
            'duration_seconds' => v::optional(v::intVal()->between(5, 300))->setName('目标时长'),
            'aspect_ratio' => v::optional(v::stringType()->length(1, 20))->setName('画幅'),
            'mode' => v::optional(v::stringType()->in(array_keys(CineEnum::$modeArr)))->setName('模式'),
            'agent_id' => v::optional(v::intVal()->positive())->setName('智能体'),
            'reference_images_json' => v::optional(v::arrayType())->setName('参考图'),
            'tool_config_json' => v::optional(v::arrayType())->setName('工具配置'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
            'title' => v::optional(v::stringType()->length(1, 120))->setName('项目标题'),
            'source_text' => v::optional(v::stringType()->length(1, 50000))->setName('原始素材'),
            'style' => v::optional(v::stringType()->length(0, 255))->setName('风格'),
            'duration_seconds' => v::optional(v::intVal()->between(5, 300))->setName('目标时长'),
            'aspect_ratio' => v::optional(v::stringType()->length(1, 20))->setName('画幅'),
            'mode' => v::optional(v::stringType()->in(array_keys(CineEnum::$modeArr)))->setName('模式'),
            'agent_id' => v::optional(v::intVal()->positive())->setName('智能体'),
            'reference_images_json' => v::optional(v::arrayType())->setName('参考图'),
            'tool_config_json' => v::optional(v::arrayType())->setName('工具配置'),
            'deliverable_markdown' => v::optional(v::stringType())->setName('交付稿'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
        ];
    }

    public static function detail(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size' => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page' => v::optional(v::intVal()->positive()),
            'title' => v::optional(v::stringType()),
            'status' => v::optional(v::oneOf(v::intVal()->in(array_keys(CineEnum::$statusArr)), v::equals(''))),
            'agent_id' => v::optional(v::intVal()->positive()),
        ];
    }

    public static function statusCount(): array
    {
        return [
            'title' => v::optional(v::stringType()),
        ];
    }

    public static function generate(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
            'agent_id' => v::optional(v::intVal()->positive())->setName('智能体'),
        ];
    }

    public static function generateKeyframes(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
            'asset_ids' => v::optional(v::arrayType())->setName('素材ID'),
        ];
    }
}

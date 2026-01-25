<?php

namespace app\validate\Ai;

use Respect\Validation\Validator as v;

class AiPromptValidate
{
    public static function add(): array
    {
        return [
            'title' => v::stringType()->length(1, 100)->setName('标题'),
            'content' => v::stringType()->notEmpty()->setName('提示词内容'),
            'category' => v::optional(v::stringType()->length(0, 50))->setName('分类'),
            'tags' => v::optional(v::arrayType())->setName('标签'),
            'variables' => v::optional(v::arrayType())->setName('变量定义'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
            'title' => v::optional(v::stringType()->length(1, 100))->setName('标题'),
            'content' => v::optional(v::stringType())->setName('提示词内容'),
            'category' => v::optional(v::stringType()->length(0, 50))->setName('分类'),
            'tags' => v::optional(v::arrayType())->setName('标签'),
            'variables' => v::optional(v::arrayType())->setName('变量定义'),
            'sort' => v::optional(v::intVal())->setName('排序'),
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
            'title' => v::optional(v::stringType()),
            'category' => v::optional(v::stringType()),
            'is_favorite' => v::optional(v::intVal()),
            'page_size' => v::optional(v::intVal()->positive()),
            'current_page' => v::optional(v::intVal()->positive()),
        ];
    }
}
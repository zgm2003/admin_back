<?php

namespace app\validate\Ai;

use Respect\Validation\Validator as v;

class AiConversationValidate
{
    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()->positive()),
            'current_page' => v::optional(v::intVal()->positive()),
            'agent_id'     => v::optional(v::intVal()->positive()),
        ];
    }

    public static function add(): array
    {
        return [
            'agent_id' => v::intVal()->positive()->setName('智能体ID'),
            'title'    => v::optional(v::stringType()->length(0, 100))->setName('标题'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'    => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
            'title' => v::optional(v::stringType()->length(0, 100))->setName('标题'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
        ];
    }
}

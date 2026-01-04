<?php

namespace app\validate\Ai;

use Respect\Validation\Validator as v;

class AiChatValidate
{
    /**
     * 发送消息校验
     * - content 必填
     * - conversation_id 可空
     * - conversation_id 为空时 agent_id 必填
     * - max_history 可选默认 20
     */
    public static function send(): array
    {
        return [
            'content'         => v::stringType()->notEmpty()->setName('消息内容'),
            'conversation_id' => v::optional(v::intVal()->positive())->setName('会话ID'),
            'agent_id'        => v::optional(v::intVal()->positive())->setName('智能体ID'),
            'max_history'     => v::optional(v::intVal()->between(1, 100))->setName('历史条数'),
        ];
    }
}

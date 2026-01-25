<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;

class NotificationTaskValidate
{
    public static function add(): array
    {
        return [
            'title' => v::stringType()->notEmpty()->length(1, 100)->setName('标题'),
            'content' => v::optional(v::stringType())->setName('内容'),
            'type' => v::optional(v::intVal()->between(1, 4))->setName('类型'),
            'level' => v::optional(v::intVal()->between(1, 2))->setName('级别'),
            'link' => v::optional(v::stringType()->length(0, 500))->setName('链接'),
            'target_type' => v::intVal()->between(1, 3)->setName('目标类型'),
            'target_ids' => v::optional(v::arrayType())->setName('目标ID列表'),
            'send_at' => v::optional(v::stringType())->setName('发送时间'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::intVal()->setName('任务ID'),
        ];
    }
}

<?php

namespace app\validate\Ai;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;
use app\enum\AiEnum;

class AiMessageValidate
{
    public static function list(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
            'page_size'       => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page'    => v::optional(v::intVal()->positive()),
            'role'            => v::optional(v::intVal()->in(array_keys(AiEnum::$roleArr))),
        ];
    }

    public static function add(): array
    {
        return [
            'conversation_id' => v::intVal()->positive()->setName('会话ID'),
            'role'            => v::intVal()->in(array_keys(AiEnum::$roleArr))->setName('角色'),
            'content'         => v::stringType()->notEmpty()->setName('消息内容'),
            'meta_json'       => v::optional(v::callback(function ($val) {
                if ($val === null || $val === '') return true;
                if (is_array($val)) return true;
                if (is_string($val)) {
                    json_decode($val);
                    return json_last_error() === JSON_ERROR_NONE;
                }
                return false;
            }))->setName('扩展信息'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
        ];
    }

    public static function feedback(): array
    {
        return [
            'id'       => v::intVal()->positive()->setName('ID'),
            'feedback' => v::optional(v::intVal()->in([1, 2]))->setName('反馈'),  // 1=点赞 2=点踩 null=取消
        ];
    }
}

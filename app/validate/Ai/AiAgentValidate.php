<?php

namespace app\validate\Ai;

use Respect\Validation\Validator as v;

class AiAgentValidate
{
    public static function add(): array
    {
        return [
            'name'         => v::stringType()->length(1, 50)->setName('智能体名称'),
            'model_id'     => v::intVal()->positive()->setName('模型ID'),
            'avatar'       => v::optional(v::stringType()->length(0, 255))->setName('头像'),
            'system_prompt'=> v::optional(v::stringType())->setName('系统提示词'),
            'mode'         => v::optional(v::stringType()->in(['chat', 'rag', 'tool', 'workflow']))->setName('模式'),
            'temperature'  => v::optional(v::floatVal()->between(0, 2))->setName('温度'),
            'max_tokens'   => v::optional(v::intVal()->positive())->setName('最大输出长度'),
            'extra_params' => v::optional(v::callback(function ($val) {
                if (is_array($val)) return true;
                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    return is_array($decoded);
                }
                return false;
            }))->setName('额外参数'),
            'status'       => v::optional(v::intVal()->in([1, 2]))->setName('状态'),
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
            'mode'         => v::optional(v::stringType()->in(['chat', 'rag', 'tool', 'workflow']))->setName('模式'),
            'temperature'  => v::optional(v::floatVal()->between(0, 2))->setName('温度'),
            'max_tokens'   => v::optional(v::intVal()->positive())->setName('最大输出长度'),
            'extra_params' => v::optional(v::callback(function ($val) {
                if (is_array($val)) return true;
                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    return is_array($decoded);
                }
                return false;
            }))->setName('额外参数'),
            'status'       => v::optional(v::intVal()->in([1, 2]))->setName('状态'),
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
            'page_size'    => v::optional(v::intVal()->positive()),
            'current_page' => v::optional(v::intVal()->positive()),
            'model_id'     => v::optional(v::intVal()->positive()),
            'status'       => v::optional(v::intVal()->in([1, 2])),
            'mode'         => v::optional(v::stringType()->in(['chat', 'rag', 'tool', 'workflow'])),
            'name'         => v::optional(v::stringType()),
        ];
    }

    public static function status(): array
    {
        return [
            'id'     => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
            'status' => v::intVal()->in([1, 2])->setName('状态'),
        ];
    }
}

<?php

namespace app\validate\Ai;

use Respect\Validation\Validator as v;
use app\enum\AiEnum;

class AiRunValidate
{
    /**
     * 列表验证规则
     */
    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()->positive()),
            'current_page' => v::optional(v::intVal()->positive()),
            'run_status'   => v::optional(v::intVal()->in(array_keys(AiEnum::$runStatusArr))),
            'agent_id'     => v::optional(v::intVal()->positive()),
            'user_id'      => v::optional(v::intVal()->positive()),
            'request_id'   => v::optional(v::stringType()),
            'date_start'   => v::optional(v::date('Y-m-d')),
            'date_end'     => v::optional(v::date('Y-m-d')),
        ];
    }

    /**
     * 详情验证规则
     */
    public static function detail(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
        ];
    }
}

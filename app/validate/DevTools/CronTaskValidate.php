<?php

namespace app\validate\DevTools;

use Respect\Validation\Validator as v;

class CronTaskValidate
{
    public static function add(): array
    {
        return [
            'name'          => v::stringType()->notEmpty()->length(1, 50)->setName('name'),
            'title'         => v::stringType()->notEmpty()->length(1, 100)->setName('title'),
            'description'   => v::optional(v::stringType()->length(0, 255))->setName('description'),
            'cron'          => v::stringType()->notEmpty()->length(1, 50)->setName('cron'),
            'cron_readable' => v::optional(v::stringType()->length(0, 50))->setName('cron_readable'),
            'handler'       => v::stringType()->notEmpty()->length(1, 255)->setName('handler'),
            'status'        => v::intVal()->between(1, 2)->setName('status'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'            => v::intVal()->positive()->setName('id'),
            'title'         => v::stringType()->notEmpty()->length(1, 100)->setName('title'),
            'description'   => v::optional(v::stringType()->length(0, 255))->setName('description'),
            'cron'          => v::stringType()->notEmpty()->length(1, 50)->setName('cron'),
            'cron_readable' => v::optional(v::stringType()->length(0, 50))->setName('cron_readable'),
            'handler'       => v::stringType()->notEmpty()->length(1, 255)->setName('handler'),
            'status'        => v::intVal()->between(1, 2)->setName('status'),
        ];
    }

    public static function status(): array
    {
        return [
            'id'     => v::intVal()->positive()->setName('id'),
            'status' => v::intVal()->between(1, 2)->setName('status'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('id'),
        ];
    }

    public static function logs(): array
    {
        return [
            'task_id'      => v::intVal()->positive()->setName('task_id'),
            'date'         => v::optional(v::arrayType())->setName('date'),
            'current_page' => v::optional(v::intVal()->positive())->setName('current_page'),
            'page_size'    => v::optional(v::intVal()->between(1, 100))->setName('page_size'),
        ];
    }
}

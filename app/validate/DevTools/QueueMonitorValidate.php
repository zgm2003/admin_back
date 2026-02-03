<?php

namespace app\validate\DevTools;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class QueueMonitorValidate
{
    /**
     * 队列状态列表（无分页，返回全量配置的队列）
     */
    public static function list(): array
    {
        return [];
    }

    /**
     * 失败任务列表
     */
    public static function failedList(): array
    {
        return [
            'queue'        => v::stringType()->notEmpty()->setName('队列名'),
            'page_size'    => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page' => v::intVal()->positive()->setName('当前页'),
        ];
    }

    /**
     * 重试失败任务
     */
    public static function retry(): array
    {
        return [
            'queue' => v::stringType()->notEmpty()->setName('队列名'),
            'index' => v::intVal()->setName('任务索引'),
        ];
    }

    /**
     * 清空等待队列
     */
    public static function clear(): array
    {
        return [
            'queue' => v::stringType()->notEmpty()->setName('队列名'),
        ];
    }

    /**
     * 清空失败任务
     */
    public static function clearFailed(): array
    {
        return [
            'queue' => v::stringType()->notEmpty()->setName('队列名'),
        ];
    }
}

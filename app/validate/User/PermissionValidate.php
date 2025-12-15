<?php

namespace app\validate\User;

use Respect\Validation\Validator as v;
use app\enum\PermissionEnum;

class PermissionValidate
{
    public static function add(): array
    {
        return [
            'type'      => v::intVal()->in([PermissionEnum::TYPE_DIR, PermissionEnum::TYPE_PAGE, PermissionEnum::TYPE_BUTTON])->setName('类型'),
            'name'      => v::length(1, 64)->setName('名称'),
            'parent_id' => v::optional(v::intVal()),
            'icon'      => v::optional(v::stringType()),
            'path'      => v::optional(v::stringType()),
            'component' => v::optional(v::stringType()),
            'i18n_key'  => v::optional(v::length(1, 128)),
            'code'      => v::optional(v::length(1, 128)),
            'sort'      => v::intVal()->between(1, 1000)->setName('排序'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'        => v::intVal()->setName('ID'),
            'type'      => v::intVal()->in([PermissionEnum::TYPE_DIR, PermissionEnum::TYPE_PAGE, PermissionEnum::TYPE_BUTTON])->setName('类型'),
            'name'      => v::length(1, 64)->setName('名称'),
            'parent_id' => v::optional(v::intVal()),
            'icon'      => v::optional(v::stringType()),
            'path'      => v::optional(v::stringType()),
            'component' => v::optional(v::stringType()),
            'i18n_key'  => v::optional(v::length(1, 128)),
            'code'      => v::optional(v::length(1, 128)),
            'sort'      => v::intVal()->between(1, 1000)->setName('排序'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }

    public static function batchEdit(): array
    {
        return [
            'ids'         => v::arrayType()->setName('ids'),
            'field'       => v::stringType()->setName('字段'),
            'description' => v::optional(v::stringType()),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()),
            'current_page' => v::optional(v::intVal()),
        ];
    }

    public static function status(): array
    {
        return [
            'id'     => v::intVal()->setName('ID'),
            'status' => v::intVal()->in([1, 2])->setName('状态'),
        ];
    }
}


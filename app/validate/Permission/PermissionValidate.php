<?php

namespace app\validate\Permission;

use Respect\Validation\Validator as v;
use app\enum\PermissionEnum;
use app\enum\CommonEnum;
use app\service\Permission\AuthPlatformService;

class PermissionValidate
{
    public static function add(): array
    {
        return [
            'platform'  => v::stringType()->in(AuthPlatformService::getAllowedPlatforms())->setName('平台'),
            'type'      => v::intVal()->in([PermissionEnum::TYPE_DIR, PermissionEnum::TYPE_PAGE, PermissionEnum::TYPE_BUTTON])->setName('类型'),
            'name'      => v::length(1, 64)->setName('名称'),
            'parent_id' => v::optional(v::intVal()),
            'icon'      => v::optional(v::stringType()),
            'path'      => v::optional(v::stringType()),
            'component' => v::optional(v::stringType()),
            'i18n_key'  => v::optional(v::length(1, 128)),
            'code'      => v::optional(v::length(1, 128)),
            'sort'      => v::intVal()->between(1, 1000)->setName('排序'),
            'show_menu' => v::optional(v::intVal()->in([CommonEnum::YES, CommonEnum::NO]))->setName('是否显示菜单'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'        => v::intVal()->setName('ID'),
            'platform'  => v::stringType()->in(AuthPlatformService::getAllowedPlatforms())->setName('平台'),
            'type'      => v::intVal()->in([PermissionEnum::TYPE_DIR, PermissionEnum::TYPE_PAGE, PermissionEnum::TYPE_BUTTON])->setName('类型'),
            'name'      => v::length(1, 64)->setName('名称'),
            'parent_id' => v::optional(v::intVal()),
            'icon'      => v::optional(v::stringType()),
            'path'      => v::optional(v::stringType()),
            'component' => v::optional(v::stringType()),
            'i18n_key'  => v::optional(v::length(1, 128)),
            'code'      => v::optional(v::length(1, 128)),
            'sort'      => v::intVal()->between(1, 1000)->setName('排序'),
            'show_menu' => v::optional(v::intVal()->in([CommonEnum::YES, CommonEnum::NO]))->setName('是否显示菜单'),
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
            'platform'     => v::stringType()->in(AuthPlatformService::getAllowedPlatforms())->setName('平台'),
            'name'         => v::optional(v::stringType()),
            'path'         => v::optional(v::stringType()),
            'type'         => v::optional(v::intVal()),
        ];
    }

    public static function status(): array
    {
        return [
            'id'     => v::intVal()->setName('ID'),
            'status' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('状态'),
        ];
    }
}

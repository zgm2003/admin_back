<?php

namespace app\validate\Permission;

use Respect\Validation\Validator as v;
use app\enum\PermissionEnum;
use app\enum\CommonEnum;
use app\service\Permission\AuthPlatformService;

class PermissionValidate
{
    public static function addBase(): array
    {
        return self::baseRules();
    }

    public static function add(int $type, bool $requireButtonParent = false): array
    {
        return array_merge(self::addBase(), self::typeRules($type, $requireButtonParent));
    }

    public static function editBase(): array
    {
        return ['id' => v::intVal()->setName('ID')] + self::baseRules();
    }

    public static function edit(int $type, bool $requireButtonParent = false): array
    {
        return array_merge(self::editBase(), self::typeRules($type, $requireButtonParent));
    }

    public static function appButtonAdd(): array
    {
        return self::add(PermissionEnum::TYPE_BUTTON);
    }

    public static function appButtonEdit(): array
    {
        return self::edit(PermissionEnum::TYPE_BUTTON);
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
            'platform' => v::stringType()->in(AuthPlatformService::getAllowedPlatforms())->setName('平台'),
            'name'     => v::optional(v::stringType()),
            'path'     => v::optional(v::stringType()),
            'type'     => v::optional(v::intVal()),
        ];
    }

    public static function status(): array
    {
        return [
            'id'     => v::intVal()->setName('ID'),
            'status' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('状态'),
        ];
    }

    private static function baseRules(): array
    {
        return [
            'platform'  => v::stringType()->in(AuthPlatformService::getAllowedPlatforms())->setName('平台'),
            'type'      => v::intVal()->in([PermissionEnum::TYPE_DIR, PermissionEnum::TYPE_PAGE, PermissionEnum::TYPE_BUTTON])->setName('类型'),
            'name'      => v::length(1, 64)->setName('名称'),
            'parent_id' => v::optional(v::intVal()->min(0)->setName('父级ID')),
            'icon'      => v::optional(v::stringType()),
            'path'      => v::optional(v::length(1, 255))->setName('路径'),
            'component' => v::optional(v::length(1, 255))->setName('组件'),
            'i18n_key'  => v::optional(v::length(1, 128))->setName('国际化键'),
            'code'      => v::optional(v::length(1, 128))->setName('权限编码'),
            'sort'      => v::intVal()->between(1, 1000)->setName('排序'),
            'show_menu' => v::optional(v::intVal()->in([CommonEnum::YES, CommonEnum::NO]))->setName('是否显示菜单'),
        ];
    }

    private static function typeRules(int $type, bool $requireButtonParent = false): array
    {
        return match ($type) {
            PermissionEnum::TYPE_DIR => [
                'i18n_key'  => v::length(1, 128)->setName('国际化键'),
                'show_menu' => v::intVal()->in([CommonEnum::YES, CommonEnum::NO])->setName('是否显示菜单'),
            ],
            PermissionEnum::TYPE_PAGE => [
                'path'      => v::length(1, 255)->setName('路径'),
                'component' => v::length(1, 255)->setName('组件'),
                'i18n_key'  => v::length(1, 128)->setName('国际化键'),
                'show_menu' => v::intVal()->in([CommonEnum::YES, CommonEnum::NO])->setName('是否显示菜单'),
            ],
            PermissionEnum::TYPE_BUTTON => array_filter([
                'parent_id' => $requireButtonParent ? v::intVal()->min(1)->setName('父节点') : null,
                'code'      => v::length(1, 128)->setName('权限编码'),
            ]),
            default => [],
        };
    }
}

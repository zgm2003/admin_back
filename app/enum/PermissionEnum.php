<?php

namespace app\enum;

class PermissionEnum
{
    const ROOT_PARENT_ID = 0;
    const LEGACY_ROOT_PARENT_ID = -1;

    const ParentCategory = self::ROOT_PARENT_ID; //父级分类
    const ChildCategory = 1; //子级分类

    public static $statusArr = [
        self::ParentCategory => "一级菜单",
        self::ChildCategory => "二级页面",

    ];

    const TYPE_DIR = 1;     // 目录
    const TYPE_PAGE = 2;    // 页面
    const TYPE_BUTTON = 3;  // 按钮

    public static $typeArr = [
        self::TYPE_DIR => "目录",
        self::TYPE_PAGE => "页面",
        self::TYPE_BUTTON => "按钮",
    ];

    public static function isRootParentId(?int $parentId): bool
    {
        return (int)$parentId <= self::ROOT_PARENT_ID;
    }

    public static function normalizeParentId(?int $parentId): int
    {
        return self::isRootParentId($parentId) ? self::ROOT_PARENT_ID : (int)$parentId;
    }

    public static function rootParentIds(): array
    {
        return [self::ROOT_PARENT_ID, self::LEGACY_ROOT_PARENT_ID];
    }
}

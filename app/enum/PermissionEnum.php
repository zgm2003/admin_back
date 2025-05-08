<?php

namespace app\enum;

class PermissionEnum
{
    const ParentCategory = -1; //父级分类
    const ChildCategory = 1; //子级分类

    public static $statusArr = [
        self::ParentCategory => "一级菜单",
        self::ChildCategory => "二级页面",

    ];

    const TYPE_MENU = 1;
    const TYPE_BUTTON = 2;

    public static $typeArr = [
        self::TYPE_MENU => "菜单",
        self::TYPE_BUTTON => "按钮",
    ];


}

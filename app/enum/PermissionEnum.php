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

    const TYPE_DIR = 1;     // 目录
    const TYPE_PAGE = 2;    // 页面
    const TYPE_BUTTON = 3;  // 按钮

    public static $typeArr = [
        self::TYPE_DIR => "目录",
        self::TYPE_PAGE => "页面",
        self::TYPE_BUTTON => "按钮",
    ];


}

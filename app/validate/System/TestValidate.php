<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class TestValidate
{
    public static function add(): array
    {
        return [
            'title' => v::stringType()->length(1, 200)->setName('标题'),
            'username' => v::stringType()->length(1, 50)->setName('用户名'),
            'nickname' => v::optional(v::stringType()->length(0, 50))->setName('昵称'),
            'email' => v::optional(v::email())->setName('邮箱'),
            'phone' => v::optional(v::regex('/^1[3-9]\d{9}$/'))->setName('手机号'),
            'password' => v::stringType()->length(1, 255)->setName('密码'),
            'avatar' => v::optional(v::stringType()->length(0, 255))->setName('头像'),
            'cover_image' => v::optional(v::stringType()->length(0, 255))->setName('封面图'),
            'status' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('状态'),
            'type' => v::optional(v::intVal()->positive())->setName('类型：1-类型A 2-类型B 3-类型C'),
            'sex' => v::intVal()->min(0)->setName('性别：0-未知 1-男 2-女'),
            'age' => v::optional(v::intVal()->positive())->setName('年龄'),
            'score' => v::optional(v::floatVal())->setName('分数'),
            'description' => v::optional(v::stringType())->setName('描述'),
            'content' => v::optional(v::stringType())->setName('内容'),
            'remark' => v::optional(v::stringType()->length(0, 500))->setName('备注'),
            'url' => v::optional(v::url())->setName('网址'),
            'published_at' => v::optional(v::stringType())->setName('发布时间'),
            'birthday' => v::optional(v::date('Y-m-d'))->setName('生日'),
            'is_vip' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('是否VIP'),
            'is_hot' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('是否热门'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
            'title' => v::optional(v::stringType()->length(0, 200))->setName('标题'),
            'username' => v::optional(v::stringType()->length(0, 50))->setName('用户名'),
            'nickname' => v::optional(v::stringType()->length(0, 50))->setName('昵称'),
            'email' => v::optional(v::email())->setName('邮箱'),
            'phone' => v::optional(v::regex('/^1[3-9]\d{9}$/'))->setName('手机号'),
            'password' => v::optional(v::stringType()->length(0, 255))->setName('密码'),
            'avatar' => v::optional(v::stringType()->length(0, 255))->setName('头像'),
            'cover_image' => v::optional(v::stringType()->length(0, 255))->setName('封面图'),
            'status' => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
            'type' => v::optional(v::intVal()->positive())->setName('类型：1-类型A 2-类型B 3-类型C'),
            'sex' => v::optional(v::intVal()->min(0))->setName('性别：0-未知 1-男 2-女'),
            'age' => v::optional(v::intVal()->positive())->setName('年龄'),
            'score' => v::optional(v::floatVal())->setName('分数'),
            'description' => v::optional(v::stringType())->setName('描述'),
            'content' => v::optional(v::stringType())->setName('内容'),
            'remark' => v::optional(v::stringType()->length(0, 500))->setName('备注'),
            'url' => v::optional(v::url())->setName('网址'),
            'published_at' => v::optional(v::stringType())->setName('发布时间'),
            'birthday' => v::optional(v::date('Y-m-d'))->setName('生日'),
            'is_vip' => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('是否VIP'),
            'is_hot' => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('是否热门'),
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
            'page_size'    => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page' => v::intVal()->positive()->setName('当前页'),
            'title'        => v::optional(v::stringType())->setName('标题'),
            'username'     => v::optional(v::stringType())->setName('用户名'),
            'nickname'     => v::optional(v::stringType())->setName('昵称'),
            'email'        => v::optional(v::stringType())->setName('邮箱'),
            'status'       => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
            'type'         => v::optional(v::intVal())->setName('类型'),
            'sex'          => v::optional(v::intVal())->setName('性别'),
            'is_vip'       => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('是否VIP'),
            'is_hot'       => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('是否热门'),
        ];
    }
}
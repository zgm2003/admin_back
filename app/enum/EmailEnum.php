<?php

namespace app\enum;

class EmailEnum
{
    const SCENE_LOGIN = 'login'; // 验证码登录
    const SCENE_FORGET = 'forget'; // 忘记密码
    const SCENE_BIND_PHONE = 'bind_phone'; // 绑定手机号
    const SCENE_BIND_EMAIL = 'bind_email'; // 绑定邮箱
    const SCENE_CHANGE_PASSWORD = 'change_password'; // 修改密码

    /**
     * 邮件主题映射
     */
    public static $sceneArr = [
        self::SCENE_LOGIN => '验证码登录',
        self::SCENE_FORGET => '找回密码',
        self::SCENE_BIND_PHONE => '绑定手机号',
        self::SCENE_BIND_EMAIL => '绑定邮箱',
        self::SCENE_CHANGE_PASSWORD => '修改密码',
    ];

    /**
     * 获取邮件主题
     */
    public static function getTheme(string $scene): string
    {
        return self::$sceneArr[$scene] ?? '验证码';
    }
}

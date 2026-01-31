<?php

namespace app\enum;

/**
 * 邮件/短信验证码场景枚举
 */
class EmailEnum
{
    // 验证码场景
    const SCENE_REGISTER = 'register';               // 注册
    const SCENE_LOGIN = 'login';                     // 验证码登录
    const SCENE_FORGET = 'forget';                   // 忘记密码
    const SCENE_BIND_PHONE = 'bind_phone';           // 绑定手机号
    const SCENE_BIND_EMAIL = 'bind_email';           // 绑定邮箱
    const SCENE_CHANGE_PASSWORD = 'change_password'; // 修改密码

    /**
     * 场景 => 邮件主题映射
     */
    public static $sceneArr = [
        self::SCENE_REGISTER => '验证码注册',
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

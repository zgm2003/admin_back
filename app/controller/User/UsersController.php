<?php

namespace app\controller\User;

use app\controller\Controller;
use app\module\User\AuthModule;
use app\module\User\ProfileModule;
use support\Request;
use Webman\RateLimiter\Annotation\RateLimiter;

class UsersController extends Controller
{
    // ==================== 认证相关（AuthModule）====================
    public function register(Request $request) { return $this->run([AuthModule::class, 'register'], $request); }
    public function getLoginConfig(Request $request) { return $this->run([AuthModule::class, 'getLoginConfig'], $request); }
    public function refresh(Request $request) { return $this->run([AuthModule::class, 'refresh'], $request); }
    public function logout(Request $request) { return $this->run([AuthModule::class, 'logout'], $request); }
    #[RateLimiter(limit: 5, ttl: 60, key: RateLimiter::IP, message: '请求过于频繁，请稍后再试')]
    #[RateLimiter(limit: 5, ttl: 60, key: [AuthModule::class, 'getRateLimitEmail'], message: '该账号操作过于频繁，请稍后再试')]
    public function forgetPassword(Request $request) { return $this->run([AuthModule::class, 'forgetPassword'], $request); }

    #[RateLimiter(limit: 10, ttl: 60, key: RateLimiter::IP, message: '登录请求过于频繁，请稍后再试')]
    #[RateLimiter(limit: 5, ttl: 60, key: [AuthModule::class, 'getRateLimitEmail'], message: '该账号尝试登录过于频繁，请1分钟后再试')]
    public function login(Request $request) { return $this->run([AuthModule::class, 'login'], $request); }

    #[RateLimiter(limit: 5, ttl: 60, key: RateLimiter::IP, message: '请求过于频繁，请稍后再试')]
    #[RateLimiter(limit: 1, ttl: 60, key: [AuthModule::class, 'getRateLimitEmail'], message: '验证码发送太频繁，请1分钟后再试')]
    #[RateLimiter(limit: 20, ttl: 86400, key: [AuthModule::class, 'getRateLimitEmail'], message: '该邮箱今日验证码发送次数已达上限')]
    public function sendCode(Request $request) { return $this->run([AuthModule::class, 'sendCode'], $request); }

    // ==================== 个人信息相关（ProfileModule）====================
    public function init(Request $request) { return $this->run([ProfileModule::class, 'init'], $request); }
    public function initPersonal(Request $request) { return $this->run([ProfileModule::class, 'initPersonal'], $request); }
    public function editPersonal(Request $request) { return $this->run([ProfileModule::class, 'editPersonal'], $request); }
    public function EditPassword(Request $request) { return $this->run([ProfileModule::class, 'editPassword'], $request); }
    public function updatePhone(Request $request) { return $this->run([ProfileModule::class, 'updatePhone'], $request); }
    public function updateEmail(Request $request) { return $this->run([ProfileModule::class, 'updateEmail'], $request); }
    public function updatePassword(Request $request) { return $this->run([ProfileModule::class, 'updatePassword'], $request); }
}

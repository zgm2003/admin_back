<?php
namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UsersModule;
use support\Request;
use Webman\RateLimiter\Annotation\RateLimiter;

class UsersController extends Controller
{
    public function register(Request $request)
    {
        $this->run([UsersModule::class, 'register'], $request);
        return $this->response();
    }

    public function getLoginConfig(Request $request)
    {
        $this->run([UsersModule::class, 'getLoginConfig'], $request);
        return $this->response();
    }

    #[RateLimiter(limit: 10, ttl: 60, key: RateLimiter::IP, message: '登录请求过于频繁，请稍后再试')]
    #[RateLimiter(limit: 5, ttl: 60, key: [UsersModule::class, 'getRateLimitEmail'], message: '该账号尝试登录过于频繁，请1分钟后再试')]
    public function login(Request $request)
    {
        $this->run([UsersModule::class, 'login'], $request);
        return $this->response();
    }

    #[RateLimiter(limit: 5, ttl: 60, key: RateLimiter::IP, message: '请求过于频繁，请稍后再试')]
    #[RateLimiter(limit: 1, ttl: 60, key: [UsersModule::class, 'getRateLimitEmail'], message: '验证码发送太频繁，请1分钟后再试')]
    #[RateLimiter(limit: 20, ttl: 86400, key: [UsersModule::class, 'getRateLimitEmail'], message: '该邮箱今日验证码发送次数已达上限')]
    public function sendCode(Request $request)
    {
        $this->run([UsersModule::class, 'sendCode'], $request);
        return $this->response();
    }

    public function forgetPassword(Request $request)
    {
        $this->run([UsersModule::class, 'forgetPassword'], $request);
        return $this->response();
    }

    public function init(Request $request)
    {
        $this->run([UsersModule::class, 'init'], $request);
        return $this->response();
    }
    public function initPersonal(Request $request)
    {
        $this->run([UsersModule::class, 'initPersonal'], $request);
        return $this->response();
    }
    public function editPersonal(Request $request)
    {
        $this->run([UsersModule::class, 'editPersonal'], $request);
        return $this->response();
    }
    public function EditPassword(Request $request)
    {
        $this->run([UsersModule::class, 'EditPassword'], $request);
        return $this->response();
    }

    public function userInfo(Request $request)
    {
        $this->run([UsersModule::class,'userInfo'],$request);
        return $this->response();
    }

    public function refresh(Request $request)
    {
        $this->run([UsersModule::class, 'refresh'], $request);
        return $this->response();
    }

    public function logout(Request $request)
    {
        $this->run([UsersModule::class, 'logout'], $request);
        return $this->response();
    }
}

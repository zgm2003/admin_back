<?php
namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UsersModule;
use support\Request;

class UsersController extends Controller
{
    public function register(Request $request)
    {
        $this->run([UsersModule::class, 'register'], $request);
        return $this->response();
    }

    public function login(Request $request)
    {
        $this->run([UsersModule::class, 'login'], $request);
        return $this->response();
    }

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
}

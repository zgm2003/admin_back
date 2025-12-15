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

    public function initList(Request $request)
    {
        $this->run([UsersModule::class,'initList'],$request);
        return $this->response();
    }

    /**
     * @OperationLog("用户编辑")
     * @Permission("user.edit")
     */
    public function editList(Request $request)
    {
        $this->run([UsersModule::class,'editList'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("用户删除")
     * @Permission("user.del")
     */
    public function delList(Request $request)
    {
        $this->run([UsersModule::class,'delList'],$request);
        return $this->response();
    }
    public function listList(Request $request)
    {
        $this->run([UsersModule::class,'listList'],$request);
        return $this->response();
    }
    public function batchEditList(Request $request)
    {
        $this->run([UsersModule::class,'batchEditList'],$request);
        return $this->response();
    }

    public function userInfo(Request $request)
    {
        $this->run([UsersModule::class,'userInfo'],$request);
        return $this->response();
    }

    public function exportList(Request $request)
    {
        $this->run([UsersModule::class,'exportList'],$request);
        return $this->response();
    }
}

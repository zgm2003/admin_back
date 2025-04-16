<?php
namespace app\controller\User;

use app\controller\Controller;
use app\module\User\RoleModule;
use support\Request;


class RoleController extends Controller{

    public function init(Request $request){

        $this->run([RoleModule::class,'init'],$request);
        return $this->response();
    }

    public function add(Request $request){

        $this->run([RoleModule::class,'add'],$request);
        return $this->response();
    }

    public function del(Request $request){
        $this->run([RoleModule::class,'del'],$request);
        return $this->response();
    }

    public function edit(Request $request){
        $this->run([RoleModule::class,'edit'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([RoleModule::class,'list'],$request);
        return $this->response();
    }

}

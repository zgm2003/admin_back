<?php
namespace app\controller\Blog;

use app\controller\Controller;
use app\module\Blog\StarModule;
use support\Request;
class StarController extends Controller{

    public function starCount(Request $request){

        $this->run([StarModule::class,'starCount'],$request);
        return $this->response();
    }
    public function isStar(Request $request){

        $this->run([StarModule::class,'isStar'],$request);
        return $this->response();
    }
    public function add(Request $request){
        $this->run([StarModule::class,'add'],$request);
        return $this->response();
    }
    public function del(Request $request){
        $this->run([StarModule::class,'del'],$request);
        return $this->response();
    }
}

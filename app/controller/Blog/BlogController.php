<?php
namespace app\controller\Blog;

use app\controller\Controller;
use app\module\Blog\BlogModule;
use support\Request;

class BlogController extends Controller{

    public function init(Request $request){

        $this->run([BlogModule::class,'init'],$request);
        return $this->response();
    }
    public function list(Request $request){
        $this->run([BlogModule::class,'list'],$request);
        return $this->response();
    }
    public function detail(Request $request){
        $this->run([BlogModule::class,'detail'],$request);
        return $this->response();
    }
    public function star(Request $request){
        $this->run([BlogModule::class,'star'],$request);
        return $this->response();
    }
}

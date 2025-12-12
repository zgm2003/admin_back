<?php
namespace app\controller\System;

use app\controller\Controller;
use app\module\System\UploadDriverModule;
use support\Request;

class UploadDriverController extends Controller{
    public function init(Request $request){
        $this->run([UploadDriverModule::class,'init'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传驱动新增")
     */
    public function add(Request $request){
        $this->run([UploadDriverModule::class,'add'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传驱动编辑")
     */
    public function edit(Request $request){
        $this->run([UploadDriverModule::class,'edit'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传驱动删除")
     */
    public function del(Request $request){
        $this->run([UploadDriverModule::class,'del'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([UploadDriverModule::class,'list'],$request);
        return $this->response();
    }
}

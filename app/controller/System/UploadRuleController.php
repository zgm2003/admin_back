<?php
namespace app\controller\System;

use app\controller\Controller;
use app\module\System\UploadRuleModule;
use support\Request;

class UploadRuleController extends Controller{
    public function init(Request $request){
        $this->run([UploadRuleModule::class,'init'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传规则新增")
     */
    public function add(Request $request){
        $this->run([UploadRuleModule::class,'add'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传规则编辑")s
     */
    public function edit(Request $request){
        $this->run([UploadRuleModule::class,'edit'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传规则删除")
     */
    public function del(Request $request){
        $this->run([UploadRuleModule::class,'del'],$request);
        return $this->response();
    }
    public function list(Request $request){
        $this->run([UploadRuleModule::class,'list'],$request);
        return $this->response();
    }
}


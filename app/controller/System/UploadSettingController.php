<?php
namespace app\controller\System;

use app\controller\Controller;
use app\module\System\UploadSettingModule;
use support\Request;

class UploadSettingController extends Controller{
    public function init(Request $request){
        $this->run([UploadSettingModule::class,'init'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传配置新增")
     * @Permission("uploadSetting.add")
     */
    public function add(Request $request){
        $this->run([UploadSettingModule::class,'add'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传配置编辑")
     * @Permission("uploadSetting.edit")
     */
    public function edit(Request $request){
        $this->run([UploadSettingModule::class,'edit'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传配置删除")
     * @Permission("uploadSetting.del")
     */
    public function del(Request $request){
        $this->run([UploadSettingModule::class,'del'],$request);
        return $this->response();
    }
    /**
     * @OperationLog("上传配置状态变更")
     * @Permission("uploadSetting.status")
     */
    public function status(Request $request){
        $this->run([UploadSettingModule::class,'status'],$request);
        return $this->response();
    }

    public function list(Request $request){
        $this->run([UploadSettingModule::class,'list'],$request);
        return $this->response();
    }
}

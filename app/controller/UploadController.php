<?php

namespace app\controller;

use app\module\UploadModule;
use support\Request;

class UploadController extends Controller
{
    public function getUploadToken(Request $request)
    {
        $this->run([UploadModule::class, 'getUploadToken'], $request);
        return $this->response();
    }
}

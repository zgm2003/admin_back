<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\UploadModule;
use support\Request;

class UploadController extends Controller
{
    public function getUploadToken(Request $request) { return $this->run([UploadModule::class, 'getUploadToken'], $request); }
}

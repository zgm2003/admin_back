<?php

namespace app\controller\App;

use app\controller\Controller;
use app\module\App\AppModule;
use support\Request;

class AppController extends Controller
{
    /** @OperationLog("APP测试") @Permission("test_test") */
    public function test(Request $request) { return $this->run([AppModule::class, 'test'], $request); }
}

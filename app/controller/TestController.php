<?php

namespace app\controller;

use app\module\TestModule;
use support\Request;

class TestController extends Controller
{
    public function test(Request $request)
    {
        $this->run([TestModule::class, 'test'], $request);
        return $this->response();
    }
}

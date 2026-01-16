<?php

namespace app\controller;

use app\module\TestModule;
use support\Request;

class TestController extends Controller
{
    public function test(Request $request) { return $this->run([TestModule::class, 'test'], $request); }
}

<?php

namespace app\controller\DevTools;

use app\controller\Controller;
use app\module\DevTools\GenModule;
use support\Request;

/**
 * 代码生成器控制器
 */
class GenController extends Controller
{
    public function tables(Request $request) { return $this->run([GenModule::class, 'tables'], $request); }
    public function columns(Request $request) { return $this->run([GenModule::class, 'columns'], $request); }
    public function preview(Request $request) { return $this->run([GenModule::class, 'preview'], $request); }
    public function generate(Request $request) { return $this->run([GenModule::class, 'generate'], $request); }
}

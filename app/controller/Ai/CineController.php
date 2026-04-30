<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\CineModule;
use support\Request;

class CineController extends Controller
{
    public function init(Request $request) { return $this->run([CineModule::class, 'init'], $request); }
    public function statusCount(Request $request) { return $this->run([CineModule::class, 'statusCount'], $request); }
    public function list(Request $request) { return $this->run([CineModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([CineModule::class, 'detail'], $request); }
    public function add(Request $request) { return $this->run([CineModule::class, 'add'], $request); }
    public function edit(Request $request) { return $this->run([CineModule::class, 'edit'], $request); }
    public function del(Request $request) { return $this->run([CineModule::class, 'del'], $request); }
    public function generate(Request $request) { return $this->run([CineModule::class, 'generate'], $request); }
    public function generateStoryboard(Request $request) { return $this->run([CineModule::class, 'generateStoryboard'], $request); }
    public function generateKeyframes(Request $request) { return $this->run([CineModule::class, 'generateKeyframes'], $request); }
}

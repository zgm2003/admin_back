<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\GoodsModule;
use support\Request;

class GoodsController extends Controller
{
    public function init(Request $request) { return $this->run([GoodsModule::class, 'init'], $request); }
    public function statusCount(Request $request) { return $this->run([GoodsModule::class, 'statusCount'], $request); }
    public function list(Request $request) { return $this->run([GoodsModule::class, 'list'], $request); }
    public function add(Request $request) { return $this->run([GoodsModule::class, 'add'], $request); }
    public function edit(Request $request) { return $this->run([GoodsModule::class, 'edit'], $request); }
    public function del(Request $request) { return $this->run([GoodsModule::class, 'del'], $request); }
    public function submit(Request $request) { return $this->run([GoodsModule::class, 'submit'], $request); }
    public function ocr(Request $request) { return $this->run([GoodsModule::class, 'ocr'], $request); }
    public function generate(Request $request) { return $this->run([GoodsModule::class, 'generate'], $request); }
    public function tts(Request $request) { return $this->run([GoodsModule::class, 'tts'], $request); }
}

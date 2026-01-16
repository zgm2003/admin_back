<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiRunModule;
use support\Request;

/**
 * AI 运行监控控制器
 */
class AiRunController extends Controller
{
    public function init(Request $request) { return $this->run([AiRunModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([AiRunModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([AiRunModule::class, 'detail'], $request); }
    public function stats(Request $request) { return $this->run([AiRunModule::class, 'statsSummary'], $request); }
    public function statsByDate(Request $request) { return $this->run([AiRunModule::class, 'statsByDate'], $request); }
    public function statsByAgent(Request $request) { return $this->run([AiRunModule::class, 'statsByAgent'], $request); }
    public function statsByUser(Request $request) { return $this->run([AiRunModule::class, 'statsByUser'], $request); }
}

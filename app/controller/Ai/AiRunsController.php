<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiRunsModule;
use support\Request;

/**
 * AI 运行监控控制器
 */
class AiRunsController extends Controller
{
    public function init(Request $request) { return $this->run([AiRunsModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([AiRunsModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([AiRunsModule::class, 'detail'], $request); }
    public function stats(Request $request) { return $this->run([AiRunsModule::class, 'statsSummary'], $request); }
    public function statsByDate(Request $request) { return $this->run([AiRunsModule::class, 'statsByDate'], $request); }
    public function statsByAgent(Request $request) { return $this->run([AiRunsModule::class, 'statsByAgent'], $request); }
    public function statsByUser(Request $request) { return $this->run([AiRunsModule::class, 'statsByUser'], $request); }
}

<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiToolsModule;
use support\Request;

/**
 * AI 工具管理控制器
 */
class AiToolsController extends Controller
{
    public function init(Request $request)          { return $this->run([AiToolsModule::class, 'init'], $request); }
    public function list(Request $request)          { return $this->run([AiToolsModule::class, 'list'], $request); }
    public function add(Request $request)           { return $this->run([AiToolsModule::class, 'add'], $request); }
    public function edit(Request $request)          { return $this->run([AiToolsModule::class, 'edit'], $request); }
    public function del(Request $request)           { return $this->run([AiToolsModule::class, 'del'], $request); }
    public function status(Request $request)        { return $this->run([AiToolsModule::class, 'status'], $request); }
    public function bindTools(Request $request)     { return $this->run([AiToolsModule::class, 'bindTools'], $request); }
    public function getAgentTools(Request $request) { return $this->run([AiToolsModule::class, 'getAgentTools'], $request); }
}

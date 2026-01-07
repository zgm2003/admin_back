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
    /**
     * 初始化（获取字典）
     */
    public function init(Request $request)
    {
        $this->run([AiRunModule::class, 'init'], $request);
        return $this->response();
    }

    /**
     * 列表
     */
    public function list(Request $request)
    {
        $this->run([AiRunModule::class, 'list'], $request);
        return $this->response();
    }

    /**
     * 详情
     */
    public function detail(Request $request)
    {
        $this->run([AiRunModule::class, 'detail'], $request);
        return $this->response();
    }
}

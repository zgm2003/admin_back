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

    /**
     * Token 统计概览
     */
    public function stats(Request $request)
    {
        $this->run([AiRunModule::class, 'statsSummary'], $request);
        return $this->response();
    }

    /**
     * 按日期统计
     */
    public function statsByDate(Request $request)
    {
        $this->run([AiRunModule::class, 'statsByDate'], $request);
        return $this->response();
    }

    /**
     * 按智能体统计
     */
    public function statsByAgent(Request $request)
    {
        $this->run([AiRunModule::class, 'statsByAgent'], $request);
        return $this->response();
    }

    /**
     * 按用户统计
     */
    public function statsByUser(Request $request)
    {
        $this->run([AiRunModule::class, 'statsByUser'], $request);
        return $this->response();
    }
}

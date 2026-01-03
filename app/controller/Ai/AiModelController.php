<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiModelModule;
use support\Request;

class AiModelController extends Controller
{
    /**
     * 初始化（获取字典）
     */
    public function init(Request $request)
    {
        $this->run([AiModelModule::class, 'init'], $request);
        return $this->response();
    }

    /**
     * 列表
     */
    public function list(Request $request)
    {
        $this->run([AiModelModule::class, 'list'], $request);
        return $this->response();
    }

    public function add(Request $request) { $this->run([AiModelModule::class, 'add'], $request); return $this->response(); }
    public function edit(Request $request) { $this->run([AiModelModule::class, 'edit'], $request); return $this->response(); }
    public function del(Request $request) { $this->run([AiModelModule::class, 'del'], $request); return $this->response(); }
    public function status(Request $request) { $this->run([AiModelModule::class, 'status'], $request); return $this->response(); }
}

<?php

namespace app\controller\Ai;

use app\controller\Controller;
use app\module\Ai\AiPromptModule;
use support\Request;

class AiPromptController extends Controller
{
    public function list(Request $request) { return $this->run([AiPromptModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([AiPromptModule::class, 'detail'], $request); }

    /** @OperationLog("提示词新增") @Permission("ai_prompt_add") */
    public function add(Request $request) { return $this->run([AiPromptModule::class, 'add'], $request); }

    /** @OperationLog("提示词编辑") @Permission("ai_prompt_edit") */
    public function edit(Request $request) { return $this->run([AiPromptModule::class, 'edit'], $request); }

    /** @OperationLog("提示词删除") @Permission("ai_prompt_del") */
    public function del(Request $request) { return $this->run([AiPromptModule::class, 'del'], $request); }

    /** 切换收藏 */
    public function toggleFavorite(Request $request) { return $this->run([AiPromptModule::class, 'toggleFavorite'], $request); }

    /** 使用（+1 use_count） */
    public function use(Request $request) { return $this->run([AiPromptModule::class, 'use'], $request); }
}
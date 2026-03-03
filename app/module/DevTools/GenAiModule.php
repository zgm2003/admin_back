<?php

namespace app\module\DevTools;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\enum\AiEnum;
use app\module\BaseModule;
use app\service\DevTools\CodeGenService;

class GenAiModule extends BaseModule
{
    /**
     * 初始化（返回代码生成可用的智能体列表）
     */
    public function init($request): array
    {
        $agents = $this->dep(AiAgentsDep::class)->getActiveByScene(AiEnum::SCENE_CODE_GEN);
        return self::success([
            'agents' => $agents->map(fn($a) => [
                'id'   => $a->id,
                'name' => $a->name,
                'mode' => $a->mode,
            ])->toArray(),
        ]);
    }

    /**
     * 获取会话历史列表（仅 code_gen 场景）
     */
    public function conversations($request): array
    {
        $param = $request->all();
        $param['user_id'] = $request->userId;
        $param['current_page'] = (int)($param['current_page'] ?? 1);
        $param['page_size'] = (int)($param['page_size'] ?? 20);

        $dep = $this->dep(AiConversationsDep::class);

        // 只查 code_gen 场景的智能体关联的会话
        $agentsDep = $this->dep(AiAgentsDep::class);
        $codeGenAgents = $agentsDep->getActiveByScene(AiEnum::SCENE_CODE_GEN);
        $agentIds = $codeGenAgents->pluck('id')->toArray();

        if (empty($agentIds)) {
            return self::success(['list' => [], 'total' => 0]);
        }

        $result = $dep->listByAgentIds($agentIds, $param['user_id'], $param['page_size'], $param['current_page']);

        return self::success([
            'list'  => $result->items(),
            'total' => $result->total(),
        ]);
    }

    /**
     * 获取指定会话的消息列表
     */
    public function messages($request): array
    {
        $param = $request->all();
        $conversationId = (int)($param['conversation_id'] ?? 0);
        self::throwIf($conversationId <= 0, '会话ID不能为空');

        // 校验会话归属
        $convDep = $this->dep(AiConversationsDep::class);
        $conv = $convDep->getByUser($conversationId, $request->userId);
        self::throwIf(!$conv, '会话不存在');

        $msgDep = $this->dep(AiMessagesDep::class);
        $messages = $msgDep->getRecentByConversationId($conversationId, 50);

        // 反转为正序
        $list = $messages->reverse()->values()->map(fn($m) => [
            'id'      => $m->id,
            'role'    => AiEnum::$roleArr[$m->role] ?? 'user',
            'content' => $m->content,
            'meta'    => $m->meta_json,
        ])->toArray();

        return self::success(['list' => $list]);
    }

    /**
     * 删除会话（软删除）
     */
    public function deleteConversation($request): array
    {
        $param = $request->all();
        $id = (int)($param['id'] ?? 0);
        self::throwIf($id <= 0, '会话ID不能为空');

        $dep = $this->dep(AiConversationsDep::class);
        $dep->deleteByUser($id, $request->userId);

        return self::success();
    }

    /**
     * 流式 AI 代码生成
     */
    public function stream(array $param, int $userId, callable $onChunk): void
    {
        $content        = $param['content'] ?? '';
        $conversationId = !empty($param['conversation_id']) ? (int)$param['conversation_id'] : null;
        $allowOverwrite = !empty($param['allow_overwrite']);
        $enableReview   = !empty($param['enable_review']);
        $enableTest     = !empty($param['enable_test']);

        self::throwIf(empty($content), '请输入需求描述');

        // 校验会话归属（防止跨用户数据访问）
        if ($conversationId) {
            $conv = $this->dep(AiConversationsDep::class)->getByUser($conversationId, $userId);
            self::throwIf(!$conv, '会话不存在');
        }

        $service = new CodeGenService();
        $service->run($content, $userId, $conversationId, $onChunk, $allowOverwrite, $enableReview, $enableTest);
    }
}

<?php

namespace app\module\Ai;

use app\dep\Ai\AiRunsDep;
use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\User\UsersDep;
use app\enum\AiEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Ai\AiRunValidate;
use RuntimeException;

/**
 * AI 运行监控模块
 */
class AiRunModule extends BaseModule
{
    protected AiRunsDep $runsDep;
    protected AiAgentsDep $agentsDep;
    protected AiConversationsDep $conversationsDep;
    protected AiMessagesDep $messagesDep;
    protected UsersDep $usersDep;

    public function __construct()
    {
        $this->runsDep = new AiRunsDep();
        $this->agentsDep = new AiAgentsDep();
        $this->conversationsDep = new AiConversationsDep();
        $this->messagesDep = new AiMessagesDep();
        $this->usersDep = new UsersDep();
    }

    /**
     * 初始化（获取字典）
     */
    public function init($request): array
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setRunStatusArr()
            ->setUserArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 列表
     */
    public function list($request): array
    {
        try {
            $param = $this->validate($request, AiRunValidate::list());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

        $res = $this->runsDep->list($param);

        // 批量查询关联数据（包含已删除，用于审计）
        $agentIds = $res->pluck('agent_id')->unique()->filter()->toArray();
        $conversationIds = $res->pluck('conversation_id')->unique()->filter()->toArray();
        
        $agentMap = $this->agentsDep->getMapByIds($agentIds);
        $conversationMap = $this->conversationsDep->getMapByIds($conversationIds);

        $list = $res->map(function ($item) use ($agentMap, $conversationMap) {
            $agent = $agentMap->get($item->agent_id);
            $conversation = $conversationMap->get($item->conversation_id);
            
            return [
                'id' => $item->id,
                'request_id' => $item->request_id,
                'user_id' => $item->user_id,
                'agent_id' => $item->agent_id,
                'agent_name' => $agent?->name ?? '-',
                'conversation_id' => $item->conversation_id,
                'conversation_title' => $conversation?->title ?: '未命名对话',
                'run_status' => $item->run_status,
                'run_status_name' => AiEnum::$runStatusArr[$item->run_status] ?? '-',
                'model_snapshot' => $item->model_snapshot,
                'prompt_tokens' => $item->prompt_tokens,
                'completion_tokens' => $item->completion_tokens,
                'total_tokens' => $item->total_tokens,
                'latency_ms' => $item->latency_ms,
                'latency_str' => $item->latency_ms ? round($item->latency_ms / 1000, 2) . 's' : '-',
                'error_msg' => $item->error_msg,
                'created_at' => $item->created_at?->toDateTimeString(),
            ];
        });

        $page = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 详情
     */
    public function detail($request): array
    {
        try {
            $param = $this->validate($request, AiRunValidate::detail());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $run = $this->runsDep->getById((int)$param['id']);
        if (!$run) {
            return self::error('记录不存在');
        }

        // 查询关联数据（包含已删除，用于审计）
        $agent = $this->agentsDep->first($run->agent_id);
        $conversation = $this->conversationsDep->first($run->conversation_id);
        $user = $this->usersDep->first($run->user_id);
        
        // 查询关联消息（包含已删除）
        $userMessage = $run->user_message_id ? $this->messagesDep->first($run->user_message_id) : null;
        $assistantMessage = $run->assistant_message_id ? $this->messagesDep->first($run->assistant_message_id) : null;

        return self::success([
            'id' => $run->id,
            'request_id' => $run->request_id,
            'user_id' => $run->user_id,
            'username' => $user?->username ?? '-',
            'agent_id' => $run->agent_id,
            'agent_name' => $agent?->name ?? '-',
            'conversation_id' => $run->conversation_id,
            'conversation_title' => $conversation?->title ?: '未命名对话',
            'run_status' => $run->run_status,
            'run_status_name' => AiEnum::$runStatusArr[$run->run_status] ?? '-',
            'model_snapshot' => $run->model_snapshot,
            'prompt_tokens' => $run->prompt_tokens,
            'completion_tokens' => $run->completion_tokens,
            'total_tokens' => $run->total_tokens,
            'latency_ms' => $run->latency_ms,
            'latency_str' => $run->latency_ms ? round($run->latency_ms / 1000, 2) . 's' : '-',
            'error_msg' => $run->error_msg,
            'meta_json' => $run->meta_json,
            'user_message' => $userMessage ? [
                'id' => $userMessage->id,
                'content' => $userMessage->content,
                'created_at' => $userMessage->created_at?->toDateTimeString(),
            ] : null,
            'assistant_message' => $assistantMessage ? [
                'id' => $assistantMessage->id,
                'content' => $assistantMessage->content,
                'meta_json' => $assistantMessage->meta_json,
                'created_at' => $assistantMessage->created_at?->toDateTimeString(),
            ] : null,
            'created_at' => $run->created_at?->toDateTimeString(),
            'updated_at' => $run->updated_at?->toDateTimeString(),
        ]);
    }
}

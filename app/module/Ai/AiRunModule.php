<?php

namespace app\module\Ai;

use app\dep\Ai\AiRunsDep;
use app\dep\Ai\AiRunStepsDep;
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
    protected AiRunStepsDep $stepsDep;
    protected AiAgentsDep $agentsDep;
    protected AiConversationsDep $conversationsDep;
    protected AiMessagesDep $messagesDep;
    protected UsersDep $usersDep;

    public function __construct()
    {
        $this->runsDep = new AiRunsDep();
        $this->stepsDep = new AiRunStepsDep();
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
            // 步骤列表
            'steps' => $this->getStepsList($run->id),
        ]);
    }

    /**
     * 获取步骤列表
     */
    private function getStepsList(int $runId): array
    {
        $steps = $this->stepsDep->getByRunId($runId);
        return $steps->map(function ($step) {
            return [
                'id' => $step->id,
                'step_no' => $step->step_no,
                'step_type' => $step->step_type,
                'step_type_name' => AiEnum::$stepTypeArr[$step->step_type] ?? '-',
                'status' => $step->status,
                'status_name' => AiEnum::$stepStatusArr[$step->status] ?? '-',
                'error_msg' => $step->error_msg,
                'latency_ms' => $step->latency_ms,
                'latency_str' => $step->latency_ms !== null ? $step->latency_ms . 'ms' : '-',
                'payload_json' => $step->payload_json,
                'created_at' => $step->created_at?->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * Token 统计
     */
    public function stats($request): array
    {
        try {
            $param = $this->validate($request, AiRunValidate::stats());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        // 默认查询最近 30 天
        if (empty($param['date_start']) && empty($param['date_end'])) {
            $param['date_start'] = date('Y-m-d', strtotime('-29 days'));
            $param['date_end'] = date('Y-m-d');
        }

        // 汇总统计
        $summary = $this->runsDep->getStats($param);
        $summary['avg_latency_str'] = $summary['avg_latency_ms'] ? round($summary['avg_latency_ms'] / 1000, 2) . 's' : '-';

        // 按日期统计
        $byDate = $this->runsDep->getStatsByDate($param);

        // 按智能体统计（并关联名称）
        $byAgent = $this->runsDep->getStatsByAgent($param);
        $agentIds = array_column($byAgent, 'agent_id');
        $agentMap = $this->agentsDep->getMapByIds($agentIds);
        foreach ($byAgent as &$item) {
            $agent = $agentMap->get($item['agent_id']);
            $item['agent_name'] = $agent?->name ?? '未知智能体';
        }

        // 按用户统计（并关联名称）
        $byUser = $this->runsDep->getStatsByUser($param);
        $userIds = array_column($byUser, 'user_id');
        $userMap = $this->usersDep->getMapByIds($userIds);
        foreach ($byUser as &$item) {
            $user = $userMap->get($item['user_id']);
            $item['username'] = $user?->username ?? '未知用户';
        }

        return self::success([
            'date_range' => [
                'start' => $param['date_start'] ?? null,
                'end' => $param['date_end'] ?? null,
            ],
            'summary' => $summary,
            'by_date' => $byDate,
            'by_agent' => $byAgent,
            'by_user' => $byUser,
        ]);
    }
}

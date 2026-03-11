<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiRunsDep;
use app\dep\Ai\AiRunStepsDep;
use app\dep\User\UsersDep;
use app\enum\AiEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\validate\Ai\AiRunsValidate;

/**
 * AI 运行监控模块
 * 负责：运行记录列表/详情、统计概览、按日期/智能体/用户维度统计
 * 详情包含关联的智能体、会话、用户、消息、步骤等完整审计信息
 */
class AiRunsModule extends BaseModule
{
    /** @var int 默认统计天数 */
    private const DEFAULT_STATS_DAYS = 29;

    /**
     * 初始化（返回运行状态、智能体字典）
     */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setRunStatusArr()
            ->setAgentArr()
            ->getDict();

        return self::success($data);
    }

    /**
     * 运行记录列表（分页，批量预加载智能体+会话信息）
     */
    public function list($request): array
    {
        $param = $this->validate($request, AiRunsValidate::list());
        $res = $this->dep(AiRunsDep::class)->list($param);

        // 批量查询关联数据（包含已删除，用于审计，只取需要的字段）
        $agentIds        = $res->pluck('agent_id')->unique()->filter()->toArray();
        $conversationIds = $res->pluck('conversation_id')->unique()->filter()->toArray();
        $agentMap        = $this->dep(AiAgentsDep::class)->getMap($agentIds, ['id', 'name']);
        $conversationMap = $this->dep(AiConversationsDep::class)->getMap($conversationIds, ['id', 'title']);

        $list = $res->map(function ($item) use ($agentMap, $conversationMap) {
            $agent        = $agentMap->get($item->agent_id);
            $conversation = $conversationMap->get($item->conversation_id);

            return [
                'id'                 => $item->id,
                'request_id'         => $item->request_id,
                'user_id'            => $item->user_id,
                'agent_id'           => $item->agent_id,
                'agent_name'         => $agent?->name ?? '-',
                'conversation_id'    => $item->conversation_id,
                'conversation_title' => $conversation?->title ?: '未命名对话',
                'run_status'         => $item->run_status,
                'run_status_name'    => AiEnum::$runStatusArr[$item->run_status] ?? '-',
                'model_snapshot'     => $item->model_snapshot,
                'prompt_tokens'      => $item->prompt_tokens,
                'completion_tokens'  => $item->completion_tokens,
                'total_tokens'       => $item->total_tokens,
                'latency_ms'         => $item->latency_ms,
                'latency_str'        => $item->latency_ms ? \round($item->latency_ms / 1000, 2) . 's' : '-',
                'error_msg'          => $item->error_msg,
                'created_at'         => $item->created_at,
            ];
        });

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 运行详情（含智能体、会话、用户、消息、步骤完整审计信息）
     */
    public function detail($request): array
    {
        $param = $this->validate($request, AiRunsValidate::detail());

        $run = $this->dep(AiRunsDep::class)->get((int)$param['id']);
        self::throwNotFound($run, '记录不存在');

        // 查询关联数据（包含已删除，用于审计）
        $agent        = $this->dep(AiAgentsDep::class)->find($run->agent_id);
        $conversation = $this->dep(AiConversationsDep::class)->find($run->conversation_id);
        $user         = $this->dep(UsersDep::class)->find($run->user_id);

        // 查询关联消息（包含已删除）
        $msgDep           = $this->dep(AiMessagesDep::class);
        $userMessage      = $run->user_message_id ? $msgDep->find($run->user_message_id) : null;
        $assistantMessage = $run->assistant_message_id ? $msgDep->find($run->assistant_message_id) : null;

        return self::success([
            'id'                 => $run->id,
            'request_id'         => $run->request_id,
            'user_id'            => $run->user_id,
            'username'           => $user?->username ?? '-',
            'agent_id'           => $run->agent_id,
            'agent_name'         => $agent?->name ?? '-',
            'conversation_id'    => $run->conversation_id,
            'conversation_title' => $conversation?->title ?: '未命名对话',
            'run_status'         => $run->run_status,
            'run_status_name'    => AiEnum::$runStatusArr[$run->run_status] ?? '-',
            'model_snapshot'     => $run->model_snapshot,
            'prompt_tokens'      => $run->prompt_tokens,
            'completion_tokens'  => $run->completion_tokens,
            'total_tokens'       => $run->total_tokens,
            'latency_ms'         => $run->latency_ms,
            'latency_str'        => $run->latency_ms ? \round($run->latency_ms / 1000, 2) . 's' : '-',
            'error_msg'          => $run->error_msg,
            'meta_json'          => $run->meta_json,
            'user_message'       => $userMessage ? [
                'id'         => $userMessage->id,
                'content'    => $userMessage->content,
                'meta_json'  => $userMessage->meta_json,
                'created_at' => $userMessage->created_at,
            ] : null,
            'assistant_message'  => $assistantMessage ? [
                'id'         => $assistantMessage->id,
                'content'    => $assistantMessage->content,
                'meta_json'  => $assistantMessage->meta_json,
                'created_at' => $assistantMessage->created_at,
            ] : null,
            'created_at'         => $run->created_at,
            'updated_at'         => $run->updated_at,
            'steps'              => $this->getStepsList($run->id),
        ]);
    }

    /**
     * 获取运行步骤列表（含步骤类型、状态、耗时、载荷）
     */
    private function getStepsList(int $runId): array
    {
        $steps = $this->dep(AiRunStepsDep::class)->getByRunId($runId);

        // 批量查询步骤关联的智能体名称
        $agentIds = $steps->pluck('agent_id')->unique()->filter()->toArray();
        $agentMap = !empty($agentIds) ? $this->dep(AiAgentsDep::class)->getMap($agentIds, ['id', 'name']) : collect();

        return $steps->map(fn($step) => [
            'id'             => $step->id,
            'step_no'        => $step->step_no,
            'step_type'      => $step->step_type,
            'step_type_name' => AiEnum::$stepTypeArr[$step->step_type] ?? '-',
            'agent_id'       => $step->agent_id,
            'agent_name'     => $step->agent_id ? ($agentMap->get($step->agent_id)?->name ?? '-') : null,
            'model_snapshot' => $step->model_snapshot ?: null,
            'status'         => $step->status,
            'status_name'    => AiEnum::$stepStatusArr[$step->status] ?? '-',
            'error_msg'      => $step->error_msg,
            'latency_ms'     => $step->latency_ms,
            'latency_str'    => $step->latency_ms !== null ? "{$step->latency_ms}ms" : '-',
            'payload_json'   => $step->payload_json,
            'created_at'     => $step->created_at,
        ])->toArray();
    }

    /**
     * 统计概览（默认最近 30 天，含总运行数、成功率、平均延迟等）
     */
    public function statsSummary($request): array
    {
        $param = $this->validate($request, AiRunsValidate::statsFilter());
        $this->applyDefaultDateRange($param);

        $summary = $this->dep(AiRunsDep::class)->getStats($param);
        $summary['avg_latency_ms'] = $summary['avg_latency_ms'] ? (int)\round($summary['avg_latency_ms']) : 0;
        $summary['success_rate']   = $summary['total_runs'] > 0
            ? \round($summary['success_runs'] / $summary['total_runs'] * 100, 1)
            : 0;

        return self::success([
            'date_range' => [
                'start' => $param['date_start'] ?? null,
                'end'   => $param['date_end'] ?? null,
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * 按日期统计（分页加载更多）
     */
    public function statsByDate($request): array
    {
        $param = $this->validate($request, AiRunsValidate::statsList());
        $this->applyDefaultDateRange($param);

        $result = $this->dep(AiRunsDep::class)->getStatsByDate($param);

        return self::success([
            'list'         => $result['list'],
            'has_more'     => $result['has_more'],
            'current_page' => $result['current_page'],
        ]);
    }

    /**
     * 按智能体统计（分页加载更多，关联智能体名称）
     */
    public function statsByAgent($request): array
    {
        $param = $this->validate($request, AiRunsValidate::statsList());
        $this->applyDefaultDateRange($param);

        $result = $this->dep(AiRunsDep::class)->getStatsByAgent($param);

        // 关联智能体名称（只取 id+name）
        $agentIds = $result['list']->pluck('agent_id')->toArray();
        $agentMap = $this->dep(AiAgentsDep::class)->getMap($agentIds, ['id', 'name']);

        $list = $result['list']->map(fn($item) => [
            'agent_id'                 => $item->agent_id,
            'agent_name'               => $agentMap->get($item->agent_id)?->name ?? '未知智能体',
            'total_runs'               => $item->total_runs,
            'total_tokens'             => $item->total_tokens,
            'total_prompt_tokens'      => $item->total_prompt_tokens,
            'total_completion_tokens'  => $item->total_completion_tokens,
            'avg_latency_ms'           => $item->avg_latency_ms,
        ]);

        return self::success([
            'list'         => $list,
            'has_more'     => $result['has_more'],
            'current_page' => $result['current_page'],
        ]);
    }

    /**
     * 按用户统计（分页加载更多，关联用户名称）
     */
    public function statsByUser($request): array
    {
        $param = $this->validate($request, AiRunsValidate::statsList());
        $this->applyDefaultDateRange($param);

        $result = $this->dep(AiRunsDep::class)->getStatsByUser($param);

        // 关联用户名称（只取 id+username）
        $userIds = $result['list']->pluck('user_id')->toArray();
        $userMap = $this->dep(UsersDep::class)->getMap($userIds, ['id', 'username']);

        $list = $result['list']->map(fn($item) => [
            'user_id'                  => $item->user_id,
            'username'                 => $userMap->get($item->user_id)?->username ?? '未知用户',
            'total_runs'               => $item->total_runs,
            'total_tokens'             => $item->total_tokens,
            'total_prompt_tokens'      => $item->total_prompt_tokens,
            'total_completion_tokens'  => $item->total_completion_tokens,
            'avg_latency_ms'           => $item->avg_latency_ms,
        ]);

        return self::success([
            'list'         => $list,
            'has_more'     => $result['has_more'],
            'current_page' => $result['current_page'],
        ]);
    }

    /**
     * 填充默认日期范围（最近 30 天）
     */
    private function applyDefaultDateRange(array &$param): void
    {
        if (empty($param['date_start']) && empty($param['date_end'])) {
            $param['date_start'] = \date('Y-m-d', \strtotime('-' . self::DEFAULT_STATS_DAYS . ' days'));
            $param['date_end']   = \date('Y-m-d');
        }
    }
}
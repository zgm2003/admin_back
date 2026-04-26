<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiRunsModel;
use app\enum\CommonEnum;
use app\enum\AiEnum;
use support\Model;

class AiRunsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiRunsModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->select([
                'id', 'request_id', 'user_id', 'agent_id', 'conversation_id',
                'run_status', 'model_snapshot', 'prompt_tokens', 'completion_tokens',
                'total_tokens', 'latency_ms', 'error_msg', 'created_at',
            ])
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['run_status']), fn($q) => $q->where('run_status', (int)$param['run_status']))
            ->when(!empty($param['agent_id']), fn($q) => $q->where('agent_id', (int)$param['agent_id']))
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int)$param['user_id']))
            ->when(!empty($param['request_id']), fn($q) => $q->where('request_id', 'like', $param['request_id'] . '%'))
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 根据 request_id 获取（幂等查询）
     */
    public function getByRequestId(string $requestId)
    {
        return $this->model
            ->where('request_id', $requestId)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 获取会话中正在运行的任务
     */
    public function getRunningByConversationId(int $conversationId)
    {
        return $this->model
            ->where('conversation_id', $conversationId)
            ->where('run_status', AiEnum::RUN_STATUS_RUNNING)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * 更新运行状态为成功
     */
    public function markSuccess(int $id, array $data): int
    {
        return $this->model
            ->where('id', $id)
            ->update(array_merge($data, [
                'run_status' => AiEnum::RUN_STATUS_SUCCESS,
            ]));
    }

    /**
     * 更新运行状态为失败
     */
    public function markFailed(int $id, string $errorMsg): int
    {
        return $this->model
            ->where('id', $id)
            ->update([
                'run_status' => AiEnum::RUN_STATUS_FAIL,
                'error_msg' => mb_substr($errorMsg, 0, 500),
            ]);
    }

    /**
     * 更新运行状态为取消
     */
    public function markCanceled(int $id): int
    {
        return $this->model
            ->where('id', $id)
            ->update([
                'run_status' => AiEnum::RUN_STATUS_CANCELED,
            ]);
    }

    /**
     * 批量更新运行状态为失败（纯数据操作）
     * @param string $threshold 时间阈值（created_at < 此时间的视为超时）
     * @param string $errorMsg 错误信息
     * @return int 影响行数
     */
    public function batchMarkFailed(string $threshold, string $errorMsg): int
    {
        return $this->model
            ->where('run_status', AiEnum::RUN_STATUS_RUNNING)
            ->where('is_del', CommonEnum::NO)
            ->where('created_at', '<', $threshold)
            ->update([
                'run_status' => AiEnum::RUN_STATUS_FAIL,
                'error_msg' => $errorMsg,
            ]);
    }

    /**
     * 获取超时的 running 任务列表（用于逐条处理）
     * @param string $timeoutAt 超时时间点（created_at < 此时间的视为超时）
     * @param int $limit 数量限制
     */
    public function getTimeoutRuns(string $timeoutAt, int $limit = 100)
    {
        return $this->model
            ->where('run_status', AiEnum::RUN_STATUS_RUNNING)
            ->where('is_del', CommonEnum::NO)
            ->where('created_at', '<', $timeoutAt)
            ->limit($limit)
            ->get();
    }

    /**
     * 统计概览（单条 SQL，条件聚合）
     */
    public function getStats(array $param): array
    {
        $row = $this->model
            ->selectRaw('
                COUNT(*) as total_runs,
                SUM(CASE WHEN run_status = ? THEN 1 ELSE 0 END) as success_runs,
                SUM(CASE WHEN run_status = ? THEN 1 ELSE 0 END) as fail_runs,
                COALESCE(SUM(total_tokens), 0) as total_tokens,
                COALESCE(SUM(prompt_tokens), 0) as total_prompt_tokens,
                COALESCE(SUM(completion_tokens), 0) as total_completion_tokens,
                AVG(latency_ms) as avg_latency_ms
            ', [AiEnum::RUN_STATUS_SUCCESS, AiEnum::RUN_STATUS_FAIL])
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->when(!empty($param['agent_id']), fn($q) => $q->where('agent_id', (int)$param['agent_id']))
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int)$param['user_id']))
            ->first();

        return [
            'total_runs' => (int)($row->total_runs ?? 0),
            'success_runs' => (int)($row->success_runs ?? 0),
            'fail_runs' => (int)($row->fail_runs ?? 0),
            'total_tokens' => (int)($row->total_tokens ?? 0),
            'total_prompt_tokens' => (int)($row->total_prompt_tokens ?? 0),
            'total_completion_tokens' => (int)($row->total_completion_tokens ?? 0),
            'avg_latency_ms' => $row->avg_latency_ms,
        ];
    }

    /**
     * 按日期统计（分页）
     */
    public function getStatsByDate(array $param): array
    {
        $query = $this->model
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total_runs, SUM(total_tokens) as total_tokens, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, AVG(latency_ms) as avg_latency_ms')
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->when(!empty($param['agent_id']), fn($q) => $q->where('agent_id', (int)$param['agent_id']))
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int)$param['user_id']))
            ->groupBy('date')
            ->orderBy('date', 'desc');

        return $this->paginateStatsQuery($query, $param);
    }

    /**
     * 按智能体统计（分页）
     */
    public function getStatsByAgent(array $param): array
    {
        $query = $this->model
            ->selectRaw('agent_id, COUNT(*) as total_runs, SUM(total_tokens) as total_tokens, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, AVG(latency_ms) as avg_latency_ms')
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->when(!empty($param['agent_id']), fn($q) => $q->where('agent_id', (int)$param['agent_id']))
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int)$param['user_id']))
            ->groupBy('agent_id')
            ->orderByRaw('total_runs DESC');

        return $this->paginateStatsQuery($query, $param);
    }

    /**
     * 按用户统计（分页）
     */
    public function getStatsByUser(array $param): array
    {
        $query = $this->model
            ->selectRaw('user_id, COUNT(*) as total_runs, SUM(total_tokens) as total_tokens, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, AVG(latency_ms) as avg_latency_ms')
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->when(!empty($param['agent_id']), fn($q) => $q->where('agent_id', (int)$param['agent_id']))
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int)$param['user_id']))
            ->groupBy('user_id')
            ->orderByRaw('total_runs DESC');

        return $this->paginateStatsQuery($query, $param);
    }

    private function paginateStatsQuery($query, array $param): array
    {
        $pageSize = (int)$param['page_size'];
        $currentPage = (int)$param['current_page'];
        $offset = ($currentPage - 1) * $pageSize;
        $total = (clone $query)->get()->count();
        $list = $query->offset($offset)->limit($pageSize)->get();

        return [
            'list' => $list,
            'page' => [
                'page_size'    => $pageSize,
                'current_page' => $currentPage,
                'total_page'   => $total > 0 ? (int)\ceil($total / $pageSize) : 0,
                'total'        => $total,
            ],
        ];
    }
}

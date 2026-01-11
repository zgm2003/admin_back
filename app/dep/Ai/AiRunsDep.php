<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiRunModel;
use app\enum\CommonEnum;
use app\enum\AiEnum;
use support\Model;

class AiRunsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiRunModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        $pageSize = $param['page_size'] ?? 20;
        $currentPage = $param['current_page'] ?? 1;

        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['run_status']), fn($q) => $q->where('run_status', (int)$param['run_status']))
            ->when(!empty($param['agent_id']), fn($q) => $q->where('agent_id', (int)$param['agent_id']))
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int)$param['user_id']))
            ->when(!empty($param['request_id']), fn($q) => $q->where('request_id', 'like', $param['request_id'] . '%'))
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
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
     * 标记超时的 running 任务为失败（定时任务用）
     * @param int $timeoutMinutes 超时分钟数
     * @return int 影响行数
     */
    public function markTimeoutAsFailed(int $timeoutMinutes = 5): int
    {
        $threshold = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));
        return $this->model
            ->where('run_status', AiEnum::RUN_STATUS_RUNNING)
            ->where('is_del', CommonEnum::NO)
            ->where('created_at', '<', $threshold)
            ->update([
                'run_status' => AiEnum::RUN_STATUS_FAIL,
                'error_msg' => '执行超时',
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
     * 统计概览
     */
    public function getStats(array $param): array
    {
        $query = $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->when(!empty($param['agent_id']), fn($q) => $q->where('agent_id', (int)$param['agent_id']))
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int)$param['user_id']));

        return [
            'total_runs' => (clone $query)->count(),
            'success_runs' => (clone $query)->where('run_status', AiEnum::RUN_STATUS_SUCCESS)->count(),
            'fail_runs' => (clone $query)->where('run_status', AiEnum::RUN_STATUS_FAIL)->count(),
            'total_tokens' => (clone $query)->sum('total_tokens') ?? 0,
            'total_prompt_tokens' => (clone $query)->sum('prompt_tokens') ?? 0,
            'total_completion_tokens' => (clone $query)->sum('completion_tokens') ?? 0,
            'avg_latency_ms' => (clone $query)->avg('latency_ms'),
        ];
    }

    /**
     * 按日期统计（分页）
     */
    public function getStatsByDate(array $param): array
    {
        $pageSize = $param['page_size'] ?? 10;
        $currentPage = $param['current_page'] ?? 1;
        $offset = ($currentPage - 1) * $pageSize;

        $query = $this->model
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total_runs, SUM(total_tokens) as total_tokens, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, AVG(latency_ms) as avg_latency_ms')
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->groupBy('date')
            ->orderBy('date', 'desc');

        $list = $query->offset($offset)->limit($pageSize + 1)->get();
        $hasMore = $list->count() > $pageSize;
        if ($hasMore) {
            $list = $list->slice(0, $pageSize);
        }

        return ['list' => $list, 'has_more' => $hasMore, 'current_page' => $currentPage];
    }

    /**
     * 按智能体统计（分页）
     */
    public function getStatsByAgent(array $param): array
    {
        $pageSize = $param['page_size'] ?? 10;
        $currentPage = $param['current_page'] ?? 1;
        $offset = ($currentPage - 1) * $pageSize;

        $query = $this->model
            ->selectRaw('agent_id, COUNT(*) as total_runs, SUM(total_tokens) as total_tokens, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, AVG(latency_ms) as avg_latency_ms')
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->groupBy('agent_id')
            ->orderByRaw('total_runs DESC');

        $list = $query->offset($offset)->limit($pageSize + 1)->get();
        $hasMore = $list->count() > $pageSize;
        if ($hasMore) {
            $list = $list->slice(0, $pageSize);
        }

        return ['list' => $list, 'has_more' => $hasMore, 'current_page' => $currentPage];
    }

    /**
     * 按用户统计（分页）
     */
    public function getStatsByUser(array $param): array
    {
        $pageSize = $param['page_size'] ?? 10;
        $currentPage = $param['current_page'] ?? 1;
        $offset = ($currentPage - 1) * $pageSize;

        $query = $this->model
            ->selectRaw('user_id, COUNT(*) as total_runs, SUM(total_tokens) as total_tokens, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, AVG(latency_ms) as avg_latency_ms')
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['date_start']), fn($q) => $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00'))
            ->when(!empty($param['date_end']), fn($q) => $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59'))
            ->groupBy('user_id')
            ->orderByRaw('total_runs DESC');

        $list = $query->offset($offset)->limit($pageSize + 1)->get();
        $hasMore = $list->count() > $pageSize;
        if ($hasMore) {
            $list = $list->slice(0, $pageSize);
        }

        return ['list' => $list, 'has_more' => $hasMore, 'current_page' => $currentPage];
    }
}

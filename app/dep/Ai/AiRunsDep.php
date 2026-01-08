<?php

namespace app\dep\Ai;

use app\model\Ai\AiRunModel;
use app\enum\CommonEnum;
use app\enum\AiEnum;

class AiRunsDep
{
    protected AiRunModel $model;

    public function __construct()
    {
        $this->model = new AiRunModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        $pageSize = $param['page_size'] ?? 20;
        $currentPage = $param['current_page'] ?? 1;

        // 只查列表需要的字段，减少数据传输
        $columns = [
            'id', 'request_id', 'user_id', 'agent_id', 'conversation_id',
            'run_status', 'model_snapshot', 'prompt_tokens', 'completion_tokens',
            'total_tokens', 'latency_ms', 'error_msg', 'created_at'
        ];

        return $this->model
            ->select($columns)
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['run_status']), function ($q) use ($param) {
                $q->where('run_status', (int)$param['run_status']);
            })
            ->when(!empty($param['agent_id']), function ($q) use ($param) {
                $q->where('agent_id', (int)$param['agent_id']);
            })
            ->when(!empty($param['user_id']), function ($q) use ($param) {
                $q->where('user_id', (int)$param['user_id']);
            })
            ->when(!empty($param['request_id']), function ($q) use ($param) {
                $q->where('request_id', 'like', $param['request_id'] . '%');
            })
            ->when(!empty($param['date_start']), function ($q) use ($param) {
                $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00');
            })
            ->when(!empty($param['date_end']), function ($q) use ($param) {
                $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59');
            })
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }

    /**
     * 创建运行记录
     */
    public function add(array $data): int
    {
        return $this->model->insertGetId($data);
    }

    /**
     * 根据 ID 获取
     */
    public function getById(int $id)
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->first();
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
     * 更新 assistant_message_id
     */
    public function updateAssistantMessageId(int $id, int $messageId): int
    {
        return $this->model
            ->where('id', $id)
            ->update(['assistant_message_id' => $messageId]);
    }

    /**
     * 批量查询，返回 id => model 的 Collection
     */
    public function getMapByIds(array $ids)
    {
        if (empty($ids)) return collect();
        return $this->model
            ->whereIn('id', array_unique($ids))
            ->get()
            ->keyBy('id');
    }

    /**
     * 统计汇总（合并为1次查询）
     */
    public function getStats(array $param): array
    {
        $result = $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('run_status', AiEnum::RUN_STATUS_SUCCESS)
            ->when(!empty($param['date_start']), function ($q) use ($param) {
                $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00');
            })
            ->when(!empty($param['date_end']), function ($q) use ($param) {
                $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59');
            })
            ->when(!empty($param['agent_id']), function ($q) use ($param) {
                $q->where('agent_id', (int)$param['agent_id']);
            })
            ->when(!empty($param['user_id']), function ($q) use ($param) {
                $q->where('user_id', (int)$param['user_id']);
            })
            ->selectRaw('
                COUNT(*) as total_runs,
                COALESCE(SUM(prompt_tokens), 0) as total_prompt_tokens,
                COALESCE(SUM(completion_tokens), 0) as total_completion_tokens,
                COALESCE(SUM(total_tokens), 0) as total_tokens,
                COALESCE(SUM(cost), 0) as total_cost,
                COALESCE(AVG(latency_ms), 0) as avg_latency_ms
            ')
            ->first();

        return [
            'total_runs' => (int)$result->total_runs,
            'total_prompt_tokens' => (int)$result->total_prompt_tokens,
            'total_completion_tokens' => (int)$result->total_completion_tokens,
            'total_tokens' => (int)$result->total_tokens,
            'total_cost' => (float)$result->total_cost,
            'avg_latency_ms' => (int)$result->avg_latency_ms,
        ];
    }

    /**
     * 按日期统计（加载更多模式）
     */
    public function getStatsByDate(array $param)
    {
        $pageSize = $param['page_size'] ?? 10;
        $currentPage = $param['current_page'] ?? 1;

        $list = $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('run_status', AiEnum::RUN_STATUS_SUCCESS)
            ->when(!empty($param['date_start']), function ($q) use ($param) {
                $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00');
            })
            ->when(!empty($param['date_end']), function ($q) use ($param) {
                $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59');
            })
            ->when(!empty($param['agent_id']), function ($q) use ($param) {
                $q->where('agent_id', (int)$param['agent_id']);
            })
            ->when(!empty($param['user_id']), function ($q) use ($param) {
                $q->where('user_id', (int)$param['user_id']);
            })
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total_runs, SUM(total_tokens) as total_tokens, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, AVG(latency_ms) as avg_latency_ms')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize + 1)  // 多查一条判断是否有下一页
            ->get();

        $hasMore = $list->count() > $pageSize;
        if ($hasMore) {
            $list = $list->slice(0, $pageSize);
        }

        return [
            'list' => $list->values(),
            'has_more' => $hasMore,
            'page_size' => $pageSize,
            'current_page' => $currentPage,
        ];
    }

    /**
     * 按智能体统计（加载更多模式）
     */
    public function getStatsByAgent(array $param)
    {
        $pageSize = $param['page_size'] ?? 10;
        $currentPage = $param['current_page'] ?? 1;

        $list = $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('run_status', AiEnum::RUN_STATUS_SUCCESS)
            ->when(!empty($param['date_start']), function ($q) use ($param) {
                $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00');
            })
            ->when(!empty($param['date_end']), function ($q) use ($param) {
                $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59');
            })
            ->when(!empty($param['agent_id']), function ($q) use ($param) {
                $q->where('agent_id', (int)$param['agent_id']);
            })
            ->when(!empty($param['user_id']), function ($q) use ($param) {
                $q->where('user_id', (int)$param['user_id']);
            })
            ->selectRaw('agent_id, COUNT(*) as total_runs, SUM(total_tokens) as total_tokens, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, AVG(latency_ms) as avg_latency_ms')
            ->groupBy('agent_id')
            ->orderByDesc('total_tokens')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->get();

        $hasMore = $list->count() > $pageSize;
        if ($hasMore) {
            $list = $list->slice(0, $pageSize);
        }

        return [
            'list' => $list->values(),
            'has_more' => $hasMore,
            'page_size' => $pageSize,
            'current_page' => $currentPage,
        ];
    }

    /**
     * 按用户统计（加载更多模式）
     */
    public function getStatsByUser(array $param)
    {
        $pageSize = $param['page_size'] ?? 10;
        $currentPage = $param['current_page'] ?? 1;

        $list = $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('run_status', AiEnum::RUN_STATUS_SUCCESS)
            ->when(!empty($param['date_start']), function ($q) use ($param) {
                $q->where('created_at', '>=', $param['date_start'] . ' 00:00:00');
            })
            ->when(!empty($param['date_end']), function ($q) use ($param) {
                $q->where('created_at', '<=', $param['date_end'] . ' 23:59:59');
            })
            ->when(!empty($param['agent_id']), function ($q) use ($param) {
                $q->where('agent_id', (int)$param['agent_id']);
            })
            ->when(!empty($param['user_id']), function ($q) use ($param) {
                $q->where('user_id', (int)$param['user_id']);
            })
            ->selectRaw('user_id, COUNT(*) as total_runs, SUM(total_tokens) as total_tokens, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens, AVG(latency_ms) as avg_latency_ms')
            ->groupBy('user_id')
            ->orderByDesc('total_tokens')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->get();

        $hasMore = $list->count() > $pageSize;
        if ($hasMore) {
            $list = $list->slice(0, $pageSize);
        }

        return [
            'list' => $list->values(),
            'has_more' => $hasMore,
            'page_size' => $pageSize,
            'current_page' => $currentPage,
        ];
    }
}

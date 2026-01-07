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

        return $this->model
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
            ->where('is_del', CommonEnum::NO)
            ->get()
            ->keyBy('id');
    }
}

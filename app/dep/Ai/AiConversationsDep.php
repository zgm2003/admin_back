<?php

namespace app\dep\Ai;

use app\model\Ai\AiConversationModel;
use app\enum\CommonEnum;

class AiConversationsDep
{
    protected AiConversationModel $model;

    public function __construct()
    {
        $this->model = new AiConversationModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     * 排序：last_message_at desc (null排后面), id desc
     */
    public function list(array $param)
    {
        $pageSize = $param['page_size'] ?? 20;
        $currentPage = $param['current_page'] ?? 1;

        // 只查列表需要的字段
        $columns = ['id', 'agent_id', 'title', 'status', 'last_message_at', 'created_at'];

        return $this->model
            ->select($columns)
            ->where('is_del', CommonEnum::NO)
            ->where('user_id', $param['user_id'])
            ->when(isset($param['status']), function ($q) use ($param) {
                $q->where('status', (int)$param['status']);
            })
            ->when(!empty($param['agent_id']), function ($q) use ($param) {
                $q->where('agent_id', (int)$param['agent_id']);
            })
            ->orderByRaw('last_message_at IS NULL ASC')
            ->orderBy('last_message_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }

    /**
     * 根据 ID 获取单条（需检查 user_id 归属）
     */
    public function getById(int $id, int $userId = null)
    {
        $query = $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    /**
     * 根据 ID 获取单条（不检查 user_id，用于管理后台）
     */
    public function getByIdWithoutUser(int $id)
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 根据 ID 获取单条（不检查 is_del）
     */
    public function first(int $id)
    {
        return $this->model->where('id', $id)->first();
    }

    public function add($data)
    {
        return $this->model->insertGetId($data);
    }

    /**
     * 更新标题（支持单个或批量）
     */
    public function updateTitle($ids, string $title, int $userId): int
    {
        $ids = is_array($ids) ? $ids : [$ids];
        return $this->model
            ->whereIn('id', $ids)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update(['title' => $title]);
    }

    public function del($ids, int $userId)
    {
        $ids = is_array($ids) ? $ids : [$ids];
        return $this->model
            ->whereIn('id', $ids)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }

    /**
     * 更新会话状态（归档/取消归档）
     */
    public function updateStatus($ids, int $status, int $userId): int
    {
        $ids = is_array($ids) ? $ids : [$ids];
        return $this->model
            ->whereIn('id', $ids)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update(['status' => $status]);
    }

    /**
     * 更新会话的最后消息时间
     */
    public function updateLastMessageAt(int $id): int
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->update(['last_message_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 批量查询，返回 id => model 的 Collection（不检查 is_del）
     */
    public function getMapByIds(array $ids)
    {
        if (empty($ids)) return collect();
        return $this->model
            ->whereIn('id', array_unique($ids))
            ->get()
            ->keyBy('id');
    }
}

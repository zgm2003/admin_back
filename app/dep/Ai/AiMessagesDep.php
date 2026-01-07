<?php

namespace app\dep\Ai;

use app\model\Ai\AiMessageModel;
use app\enum\CommonEnum;

class AiMessagesDep
{
    protected AiMessageModel $model;

    public function __construct()
    {
        $this->model = new AiMessageModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     * 按 id asc 排序，保证对话从旧到新
     */
    public function list(array $param)
    {
        $pageSize = $param['page_size'] ?? 20;
        $currentPage = $param['current_page'] ?? 1;

        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('conversation_id', $param['conversation_id'])
            ->when(isset($param['role']) && $param['role'] !== '', function ($q) use ($param) {
                $q->where('role', (int)$param['role']);
            })
            ->orderBy('id', 'desc')  // 最新消息在前，前端需要 reverse
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }

    /**
     * 根据 ID 获取单条
     */
    public function getById(int $id)
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

    public function del($id, $data)
    {
        if (!is_array($id)) $id = [$id];
        return $this->model->whereIn('id', $id)->update($data);
    }

    /**
     * 获取指定会话的最近消息（按 id desc，返回后需调用方反转）
     */
    public function getRecentByConversationId(int $conversationId, int $limit)
    {
        return $this->model
            ->where('conversation_id', $conversationId)
            ->where('is_del', CommonEnum::NO)
            ->whereIn('role', [1, 2]) // 只取 user 和 assistant
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
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
}

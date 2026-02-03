<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiConversationModel;
use app\enum\CommonEnum;
use support\Model;

class AiConversationsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiConversationModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     * 排序：last_message_at desc (null排后面), id desc
     */
    public function list(array $param)
    {
        $columns = ['id', 'agent_id', 'title', 'status', 'last_message_at', 'created_at'];

        return $this->model
            ->select($columns)
            ->where('is_del', CommonEnum::NO)
            ->where('user_id', $param['user_id'])
            ->when(isset($param['status']), fn($q) => $q->where('status', (int)$param['status']))
            ->when(!empty($param['agent_id']), fn($q) => $q->where('agent_id', (int)$param['agent_id']))
            ->orderByRaw('last_message_at IS NULL ASC')
            ->orderBy('last_message_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 根据 ID 获取（需检查 user_id 归属）
     * 覆盖父类方法，增加用户校验
     */
    public function getByUser(int $id, int $userId)
    {
        return $this->model
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 更新标题
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

    /**
     * 删除会话（软删，需检查 user_id）
     */
    public function deleteByUser($ids, int $userId): int
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
}

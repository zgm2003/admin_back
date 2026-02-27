<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiMessagesModel;
use app\enum\CommonEnum;
use support\Model;

class AiMessagesDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiMessagesModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     * 按 id desc 排序，前端需要 reverse
     */
    public function list(array $param)
    {
        $columns = ['id', 'conversation_id', 'role', 'content', 'meta_json', 'created_at'];

        return $this->model
            ->select($columns)
            ->where('is_del', CommonEnum::NO)
            ->where('conversation_id', $param['conversation_id'])
            ->when(isset($param['role']) && $param['role'] !== '', fn($q) => $q->where('role', (int)$param['role']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 获取指定会话的最近消息（按 id desc，返回后需调用方反转）
     */
    public function getRecentByConversationId(int $conversationId, int $limit)
    {
        return $this->model
            ->select(['id', 'role', 'content', 'meta_json'])
            ->where('conversation_id', $conversationId)
            ->where('is_del', CommonEnum::NO)
            ->whereIn('role', [1, 2]) // 只取 user 和 assistant
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 更新消息内容
     */
    public function updateContent(int $id, string $content): int
    {
        return $this->model
            ->where('id', $id)
            ->update(['content' => $content]);
    }

    /**
     * 软删除指定会话中 id 大于给定值的所有消息
     */
    public function softDeleteAfter(int $conversationId, int $afterId): int
    {
        return $this->model
            ->where('conversation_id', $conversationId)
            ->where('id', '>', $afterId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }

    /**
     * 更新消息反馈（点赞/点踩）
     */
    public function updateFeedback(int $id, ?int $feedback): int
    {
        $message = $this->get($id);
        if (!$message) {
            return 0;
        }

        $metaJson = $message->meta_json ?? [];
        if ($feedback === null) {
            unset($metaJson['feedback']);
        } else {
            $metaJson['feedback'] = $feedback;
        }

        return $this->model
            ->where('id', $id)
            ->update(['meta_json' => $metaJson ?: null]);
    }
}

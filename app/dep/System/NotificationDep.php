<?php

namespace app\dep\System;

use app\dep\BaseDep;
use app\model\System\NotificationModel;
use app\enum\CommonEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use support\Model;

class NotificationDep extends BaseDep
{
    /** 列表页查询字段（排除大文本，减少 IO） */
    private const LIST_COLUMNS = ['id', 'title', 'content', 'type', 'level', 'link', 'is_read', 'created_at'];

    protected function createModel(): Model
    {
        return new NotificationModel();
    }

    /**
     * 用户+平台 公共 scope
     */
    private function scopeUser(int $userId, string $platform): Builder
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where(fn(Builder $q) => $q->where('platform', $platform)->orWhere('platform', 'all'))
            ->where('is_del', CommonEnum::NO);
    }

    /**
     * 获取用户未读通知数
     */
    public function unreadCount(int $userId, string $platform): int
    {
        return $this->scopeUser($userId, $platform)
            ->where('is_read', CommonEnum::NO)
            ->count();
    }

    /**
     * 标记已读（支持单个/批量/全部，带用户校验）
     */
    public function markRead(int $userId, string $platform, $id = null): int
    {
        $query = $this->scopeUser($userId, $platform)
            ->where('is_read', CommonEnum::NO);

        if ($id !== null) {
            $ids = \is_array($id) ? $id : [$id];
            $query->whereIn('id', $ids);
        }

        return $query->update(['is_read' => CommonEnum::YES]);
    }

    /**
     * 获取用户通知列表（普通分页，支持筛选）
     */
    public function pageListByUser(int $userId, string $platform, array $param): LengthAwarePaginator
    {
        return $this->scopeUser($userId, $platform)
            ->select(self::LIST_COLUMNS)
            ->when(isset($param['type']) && $param['type'] !== '', fn($q) => $q->where('type', (int)$param['type']))
            ->when(isset($param['level']) && $param['level'] !== '', fn($q) => $q->where('level', (int)$param['level']))
            ->when(isset($param['is_read']) && $param['is_read'] !== '', fn($q) => $q->where('is_read', (int)$param['is_read']))
            ->when(!empty($param['keyword']), fn($q) => $q->where('title', 'like', $param['keyword'] . '%'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 删除通知（支持单个/批量，带用户校验）
     */
    public function deleteByUser($ids, int $userId): int
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return 0;
        }
        return $this->model->newQuery()
            ->whereIn('id', $ids)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }
}

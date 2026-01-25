<?php

namespace app\dep\System;

use app\dep\BaseDep;
use app\model\System\NotificationModel;
use app\enum\CommonEnum;
use support\Model;

class NotificationDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new NotificationModel();
    }

    /**
     * 获取用户通知列表（分页）
     */
    public function list(int $userId, array $param)
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->when(isset($param['is_read']) && $param['is_read'] !== '', 
                fn($q) => $q->where('is_read', $param['is_read']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 获取用户未读通知数
     */
    public function unreadCount(int $userId): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_read', CommonEnum::NO)
            ->where('is_del', CommonEnum::NO)
            ->count();
    }

    /**
     * 标记单条已读
     */
    public function markRead(int $id, int $userId): int
    {
        return $this->model
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_read' => CommonEnum::YES]);
    }

    /**
     * 标记全部已读
     */
    public function markAllRead(int $userId): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_read', CommonEnum::NO)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_read' => CommonEnum::YES]);
    }

    /**
     * 创建通知
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * 删除通知（带用户校验）
     */
    public function deleteByUser(int $id, int $userId): int
    {
        return $this->model
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }
}

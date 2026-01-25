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
     * 获取用户通知列表（游标分页）
     */
    public function listByUser(int $userId, array $param): array
    {
        return $this->listCursor(
            $param,
            fn($q) => $q->where('user_id', $userId)
                ->when(isset($param['is_read']) && $param['is_read'] !== '', 
                    fn($q) => $q->where('is_read', $param['is_read']))
        );
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
     * 标记已读（传id标记单条，不传标记全部）
     */
    public function markRead(int $userId, ?int $id = null): int
    {
        $query = $this->model
            ->where('user_id', $userId)
            ->where('is_read', CommonEnum::NO)
            ->where('is_del', CommonEnum::NO);
        
        if ($id) $query->where('id', $id);
        
        return $query->update(['is_read' => CommonEnum::YES]);
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

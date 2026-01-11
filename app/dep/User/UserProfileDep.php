<?php

namespace app\dep\User;

use app\dep\BaseDep;
use app\model\User\UserProfileModel;
use support\Model;

/**
 * 用户资料 Dep
 * 注意：user_profiles 表没有 is_del 字段，不使用软删除
 */
class UserProfileDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UserProfileModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据用户ID查询
     */
    public function findByUserId(int $userId)
    {
        return $this->model->where('user_id', $userId)->first();
    }

    /**
     * 批量获取用户 Profile
     * @return \Illuminate\Support\Collection user_id => ProfileModel
     */
    public function getMapByUserIds(array $userIds)
    {
        if (empty($userIds)) {
            return collect();
        }
        return $this->model
            ->whereIn('user_id', array_unique($userIds))
            ->get()
            ->keyBy('user_id');
    }

    // ==================== 写入方法 ====================

    /**
     * 根据用户ID更新
     */
    public function updateByUserId(int $userId, array $data): int
    {
        return $this->model->where('user_id', $userId)->update($data);
    }

    /**
     * 覆盖父类方法：此表不支持软删除
     */
    public function get(int $id)
    {
        return $this->find($id);
    }

    /**
     * 覆盖父类方法：此表不支持软删除
     */
    public function delete($ids): int
    {
        $ids = is_array($ids) ? $ids : [$ids];
        return $this->model->whereIn('id', $ids)->delete();
    }
}

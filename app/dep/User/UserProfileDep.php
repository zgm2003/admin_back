<?php

namespace app\dep\User;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\User\UserProfileModel;
use support\Model;

/**
 * User profile Dep
 */
class UserProfileDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UserProfileModel();
    }

    // ==================== Query ====================

    public function findByUserId(int $userId)
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection user_id => ProfileModel
     */
    public function getMapByUserIds(array $userIds)
    {
        if (empty($userIds)) {
            return collect();
        }

        return $this->model
            ->whereIn('user_id', array_unique($userIds))
            ->where('is_del', CommonEnum::NO)
            ->get()
            ->keyBy('user_id');
    }

    // ==================== Write ====================

    public function updateByUserId(int $userId, array $data): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update($data);
    }

    public function updateByUserIds(array $userIds, array $data): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return $this->model
            ->whereIn('user_id', array_values(array_unique($userIds)))
            ->where('is_del', CommonEnum::NO)
            ->update($data);
    }
}

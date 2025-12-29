<?php

namespace app\dep\User;

use app\model\User\UserProfileModel;

class UserProfileDep
{
    public $model;

    public function __construct()
    {
        $this->model = new UserProfileModel();
    }

    public function firstByUserId(int $userId)
    {
        return $this->model->where('user_id', $userId)->first();
    }

    public function editByUserId(int $userId, array $data)
    {
        return $this->model->where('user_id', $userId)->update($data);
    }

    public function add(array $data)
    {
        return $this->model->insertGetId($data);
    }

    /**
     * 批量获取用户Profile(按user_id列表)
     * @param array $userIds
     * @return \Illuminate\Support\Collection  user_id => ProfileModel
     */
    public function getMapByUserIds(array $userIds)
    {
        if (empty($userIds)) return collect();
        return $this->model
            ->whereIn('user_id', array_unique($userIds))
            ->get()
            ->keyBy('user_id');
    }
}


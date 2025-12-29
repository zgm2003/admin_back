<?php

namespace app\dep\User;

use app\enum\CommonEnum;
use app\model\User\UserSessionsModel;


class UserSessionsDep
{
    public $model;
    protected string $table = 'user_sessions';

    public function __construct()
    {
        $this->model = new UserSessionsModel();
    }

    public function add(array $data): int
    {
        return (int) $this->model->insertGetId($data);
    }

    public function firstValidByAccessHash(string $accessHash)
    {
        $now = date('Y-m-d H:i:s');
        return $this->model
            ->where('access_token_hash', $accessHash)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->where('expires_at', '>', $now)
            ->first();
    }

    public function firstValidByRefreshHash(string $refreshHash)
    {
        $now = date('Y-m-d H:i:s');
        return $this->model
            ->where('refresh_token_hash', $refreshHash)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->where('refresh_expires_at', '>', $now)
            ->first();
    }

    public function rotateById(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return (int) $this->model->where('id', $id)->update($data);
    }

    public function revokeById(int $id): int
    {
        return (int) $this->model->where('id', $id)->update([
            'revoked_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function revokeByUserPlatform(int $userId, string $platform): int
    {
        return (int) $this->model
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->update([
                'revoked_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function touch(int $id): int
    {
        return (int) $this->model->where('id', $id)->update([
            'last_seen_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function listActiveByUserPlatform(int $userId, string $platform)
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->get();
    }

    public function firstLatestActiveByUserPlatform(int $userId, string $platform)
    {
        $now = date('Y-m-d H:i:s');
        return $this->model
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->where('refresh_expires_at', '>', $now)
            ->orderByDesc('id')
            ->first();
    }

    public function firstLatestByUserId(int $userId)
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->orderByDesc('id')
            ->first();
    }

    public function delById($id)
    {
        $ids = is_array($id) ? $id : [$id];
        return $this->model->whereIn('id', $ids)->update(['is_del' => CommonEnum::YES]);
    }

    /**
     * 批量获取用户最新活跃会话(按user_id+platform)
     * @param array $userIds
     * @param string $platform
     * @return \Illuminate\Support\Collection  user_id => SessionModel
     */
    public function getLatestActiveMapByUserIds(array $userIds, string $platform)
    {
        if (empty($userIds)) return collect();
        $now = date('Y-m-d H:i:s');
        
        // 使用子查询获取每个用户的最新会话ID
        $subQuery = $this->model
            ->selectRaw('MAX(id) as max_id')
            ->whereIn('user_id', array_unique($userIds))
            ->where('platform', $platform)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->where('refresh_expires_at', '>', $now)
            ->groupBy('user_id');
        
        return $this->model
            ->whereIn('id', $subQuery)
            ->get()
            ->keyBy('user_id');
    }
}

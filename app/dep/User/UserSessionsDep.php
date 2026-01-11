<?php

namespace app\dep\User;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\User\UserSessionsModel;
use support\Model;

class UserSessionsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UserSessionsModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据 access_token_hash 查询有效会话
     */
    public function findValidByAccessHash(string $accessHash)
    {
        $now = date('Y-m-d H:i:s');
        return $this->model
            ->where('access_token_hash', $accessHash)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->where('expires_at', '>', $now)
            ->first();
    }

    /**
     * 根据 refresh_token_hash 查询有效会话
     */
    public function findValidByRefreshHash(string $refreshHash)
    {
        $now = date('Y-m-d H:i:s');
        return $this->model
            ->where('refresh_token_hash', $refreshHash)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->where('refresh_expires_at', '>', $now)
            ->first();
    }

    /**
     * 获取用户在指定平台的最新有效会话
     */
    public function findLatestActiveByUserPlatform(int $userId, string $platform)
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

    /**
     * 获取用户最新有效会话
     */
    public function findLatestByUserId(int $userId)
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 获取用户在指定平台的所有活跃会话
     */
    public function listActiveByUserPlatform(int $userId, string $platform)
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->get();
    }

    /**
     * 批量获取用户最新活跃会话
     * @return \Illuminate\Support\Collection user_id => SessionModel
     */
    public function getLatestActiveMapByUserIds(array $userIds, string $platform)
    {
        if (empty($userIds)) {
            return collect();
        }
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

    // ==================== 写入方法 ====================

    /**
     * 轮换 Token
     */
    public function rotate(int $id, array $data): int
    {
        return $this->model->where('id', $id)->update($data);
    }

    /**
     * 撤销会话
     */
    public function revoke(int $id): int
    {
        return $this->model->where('id', $id)->update([
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 撤销用户在指定平台的所有会话
     */
    public function revokeByUserPlatform(int $userId, string $platform): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 更新最后活跃时间
     */
    public function touch(int $id): int
    {
        return $this->model->where('id', $id)->update([
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

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
        $count = $this->model->where('id', $id)->update([
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        // 清除统计缓存
        if ($count > 0) {
            \support\Cache::delete('session_stats_active');
        }

        return $count;
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

    // ==================== 会话管理方法 ====================

    /**
     * 带用户信息的会话列表（分页）
     */
    public function listWithUser(array $param)
    {
        $pageSize = $param['page_size'] ?? 20;
        $currentPage = $param['current_page'] ?? 1;

        $query = $this->model
            ->leftJoin('users', 'user_sessions.user_id', '=', 'users.id')
            ->select([
                'user_sessions.*',
                'users.username',
            ])
            ->where('user_sessions.is_del', CommonEnum::NO);

        // 筛选条件
        if (!empty($param['username'])) {
            $query->where('users.username', 'like', $param['username'] . '%');
        }
        if (!empty($param['platform'])) {
            $query->where('user_sessions.platform', $param['platform']);
        }
        if (!empty($param['status'])) {
            $now = date('Y-m-d H:i:s');
            if ($param['status'] === 'active') {
                $query->whereNull('user_sessions.revoked_at')
                    ->where('user_sessions.refresh_expires_at', '>', $now);
            } elseif ($param['status'] === 'expired') {
                $query->whereNull('user_sessions.revoked_at')
                    ->where('user_sessions.refresh_expires_at', '<=', $now);
            } elseif ($param['status'] === 'revoked') {
                $query->whereNotNull('user_sessions.revoked_at');
            }
        }

        $query->orderByDesc('user_sessions.last_seen_at');

        return $query->paginate($pageSize, ['*'], 'page', $currentPage);
    }

    /**
     * 获取会话统计数据（带短缓存）
     */
    public function getStats(): array
    {
        $cacheKey = 'session_stats_active';
        $cached = \support\Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $now = date('Y-m-d H:i:s');

        // 一次查询拿到总数和平台分布（基于 refresh_token 过期时间）
        $stats = $this->model
            ->where('is_del', CommonEnum::NO)
            ->whereNull('revoked_at')
            ->where('refresh_expires_at', '>', $now)
            ->selectRaw('COUNT(*) as total, platform')
            ->groupBy('platform')
            ->pluck('total', 'platform')
            ->toArray();

        $result = [
            'total_active' => array_sum($stats),
            'platform_distribution' => [
                'admin' => $stats['admin'] ?? 0,
                'app' => $stats['app'] ?? 0,
            ],
        ];

        // 缓存 30 秒
        \support\Cache::set($cacheKey, $result, 30);

        return $result;
    }

    /**
     * 批量撤销会话
     */
    public function batchRevoke(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $count = $this->model
            ->whereIn('id', $ids)
            ->whereNull('revoked_at')
            ->where('is_del', CommonEnum::NO)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);

        // 清除统计缓存
        if ($count > 0) {
            \support\Cache::delete('session_stats_active');
        }

        return $count;
    }

    /**
     * 根据 ID 获取会话（带 access_token_hash）
     */
    public function getByIds(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }
        return $this->model
            ->whereIn('id', $ids)
            ->where('is_del', CommonEnum::NO)
            ->get();
    }
}

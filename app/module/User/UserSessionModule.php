<?php

namespace app\module\User;

use app\dep\User\UserSessionsDep;
use app\module\BaseModule;
use support\Redis;

/**
 * 用户会话管理模块
 */
class UserSessionModule extends BaseModule
{
    protected UserSessionsDep $sessionsDep;

    public function __construct()
    {
        $this->sessionsDep = new UserSessionsDep();
    }

    /**
     * 会话列表
     */
    public function list($request): array
    {
        $param = $request->all();
        $paginator = $this->sessionsDep->listWithUser($param);

        // 计算状态（基于 refresh_token 过期时间）
        $now = date('Y-m-d H:i:s');
        $data = $paginator->getCollection()->map(function ($item) use ($now) {
            $item->status = $item->revoked_at ? 'revoked' : ($item->refresh_expires_at <= $now ? 'expired' : 'active');
            return $item;
        });

        return self::success([
            'list' => $data,
            'page' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'page_size' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * 会话统计
     */
    public function stats($request): array
    {
        $stats = $this->sessionsDep->getStats();
        return self::success($stats);
    }

    /**
     * 单个踢下线
     */
    public function kick($request): array
    {
        $id = $request->post('id');
        self::throwIf(!$id, '缺少会话ID');

        // 获取会话信息
        $sessions = $this->sessionsDep->getByIds([$id]);
        $session = $sessions->first();
        self::throwUnless($session, '会话不存在');

        // 不能踢自己当前会话
        $currentSessionId = $request->sessionId ?? null;
        self::throwIf($currentSessionId && (int)$currentSessionId === (int)$id, '不能踢自己的当前会话');

        // 撤销会话
        $this->sessionsDep->revoke($id);

        // 清除 Redis Token 缓存
        if (!empty($session->access_token_hash)) {
            Redis::connection('token')->del($session->access_token_hash);
        }

        // 清除会话指针
        $this->clearSessionPointer($session->user_id, $session->platform, $id);

        return self::success([], '踢下线成功');
    }

    /**
     * 批量踢下线
     */
    public function batchKick($request): array
    {
        $ids = $request->post('ids', []);
        self::throwIf(empty($ids), '请选择要踢下线的会话');

        // 获取会话信息
        $sessions = $this->sessionsDep->getByIds($ids);
        self::throwIf($sessions->isEmpty(), '未找到有效会话');

        // 过滤掉当前会话，收集要清除的 Redis keys
        $currentSessionId = $request->sessionId ?? null;
        $validIds = [];
        $redisKeys = [];

        foreach ($sessions as $session) {
            if ($currentSessionId && (int)$currentSessionId === (int)$session->id) {
                continue;
            }
            $validIds[] = $session->id;

            if (!empty($session->access_token_hash)) {
                $redisKeys[] = $session->access_token_hash;
            }

            // 清除会话指针
            $this->clearSessionPointer($session->user_id, $session->platform, $session->id);
        }

        // 批量清除 Redis Token 缓存（一次调用）
        if (!empty($redisKeys)) {
            Redis::connection('token')->del(...$redisKeys);
        }

        // 批量撤销
        $count = $this->sessionsDep->batchRevoke($validIds);

        return self::success(['count' => $count], "成功踢下线 {$count} 个会话");
    }

    /**
     * 清除会话指针（如果匹配）
     */
    private function clearSessionPointer(int $userId, string $platform, int $sessionId): void
    {
        if (!$platform) return;
        $key = "cur_sess:" . strtolower(trim($platform)) . ":{$userId}";
        $currentPtr = Redis::connection('token')->get($key);
        if ($currentPtr && (int)$currentPtr === (int)$sessionId) {
            Redis::connection('token')->del($key);
        }
    }
}

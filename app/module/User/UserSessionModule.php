<?php

namespace app\module\User;

use app\dep\User\UserSessionsDep;
use app\module\BaseModule;
use app\service\System\AuthPlatformService;
use app\service\User\SessionService;
use app\validate\User\UserSessionValidate;

/**
 * 用户会话管理模块
 */
class UserSessionModule extends BaseModule
{
    /**
     * 会话列表
     */
    public function list($request): array
    {
        $param = $this->validate($request, UserSessionValidate::list());
        $paginator = $this->dep(UserSessionsDep::class)->listWithUser($param);

        $now = date('Y-m-d H:i:s');
        $list = $paginator->getCollection()->map(function ($item) use ($now) {
            $item->status = $item->revoked_at ? 'revoked' : ($item->refresh_expires_at <= $now ? 'expired' : 'active');
            $item->platform_name = AuthPlatformService::getPlatformName($item->platform);
            return $item;
        });

        $page = [
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'page_size'    => $paginator->perPage(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 会话统计
     */
    public function stats($request): array
    {
        return self::success($this->dep(UserSessionsDep::class)->getStats());
    }

    /**
     * 单个踢下线
     */
    public function kick($request): array
    {
        $param = $this->validate($request, UserSessionValidate::kick());

        $currentSessionId = $request->sessionId ?? null;
        self::throwIf($currentSessionId && (int)$currentSessionId === (int)$param['id'], '不能踢自己的当前会话');

        $count = SessionService::kick([$param['id']]);
        self::throwIf($count === 0, '会话不存在或已失效');

        return self::success([], '踢下线成功');
    }

    /**
     * 批量踢下线
     */
    public function batchKick($request): array
    {
        $param = $this->validate($request, UserSessionValidate::batchKick());

        // 过滤掉当前会话
        $currentSessionId = $request->sessionId ?? null;
        $ids = $currentSessionId
            ? array_filter($param['ids'], fn($id) => (int)$id !== (int)$currentSessionId)
            : $param['ids'];

        self::throwIf(empty($ids), '未找到有效会话');

        $count = SessionService::kick($ids);

        return self::success(['count' => $count], "成功踢下线 {$count} 个会话");
    }
}

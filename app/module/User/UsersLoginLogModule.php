<?php

namespace app\module\User;

use app\dep\User\UsersDep;
use app\dep\User\UsersLoginLogDep;
use app\enum\SystemEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\service\Permission\AuthPlatformService;
use app\validate\User\UsersLoginLogValidate;

/**
 * 用户登录日志模块
 * 负责：日志列表初始化、分页列表
 */
class UsersLoginLogModule extends BaseModule
{
    // ==================== 公开接口 ====================

    /**
     * 初始化（获取字典数据）
     */
    public function init(): array
    {
        $dict = $this->svc(DictService::class)
            ->setPlatformArr()
            ->setLoginTypeArr()
            ->getDict();

        return self::success(['dict' => $dict]);
    }

    /** 登录日志列表（普通分页） */
    public function list($request): array
    {
        $param = $this->validate($request, UsersLoginLogValidate::list());
        $paginator = $this->dep(UsersLoginLogDep::class)->list($param);

        // 批量预加载用户数据
        $userMap = $this->preloadUsers($paginator);

        $list = $paginator->map(fn($item) => $this->formatLogItem($item, $userMap));

        $page = [
            'page_size'    => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'total_page'   => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ];

        return self::paginate($list, $page);
    }

    // ==================== 私有方法 ====================

    /**
     * 批量预加载用户数据
     */
    private function preloadUsers($collection): mixed
    {
        $userIds = $collection->pluck('user_id')->filter()->unique()->toArray();
        return $this->dep(UsersDep::class)->getMap($userIds);
    }

    /**
     * 格式化单条日志数据
     */
    private function formatLogItem($item, $userMap): array
    {
        $username = 'Unknown';
        if (!empty($item['user_id'])) {
            $username = $userMap->get($item['user_id'])?->username ?? 'Unknown';
        }

        return [
            'id'              => $item['id'],
            'user_name'       => $username,
            'login_account'   => $item['login_account'],
            'login_type'      => $item['login_type'],
            'login_type_name' => SystemEnum::$loginTypeArr[$item['login_type']] ?? '',
            'platform'        => $item['platform'],
            'platform_name'   => AuthPlatformService::getPlatformName($item['platform']),
            'ip'              => $item['ip'],
            'ua'              => $item['ua'],
            'is_success'      => $item['is_success'],
            'reason'          => $item['reason'] ?? '',
            'created_at'      => $item['created_at'],
        ];
    }
}

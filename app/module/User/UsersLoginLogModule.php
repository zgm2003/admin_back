<?php

namespace app\module\User;

use app\dep\User\UsersLoginLogDep;
use app\dep\User\UsersDep;
use app\module\BaseModule;
use app\service\DictService;
use app\enum\SystemEnum;

class UsersLoginLogModule extends BaseModule
{
    protected UsersLoginLogDep $usersLoginLogDep;
    protected UsersDep $usersDep;

    public function __construct()
    {
        $this->usersLoginLogDep = new UsersLoginLogDep();
        $this->usersDep = new UsersDep();
    }

    // 用户列表不需要了，前端使用远程搜索
    public function init()
    {
        $dictService = new DictService();

        $dict = $dictService
            ->setPlatformArr()
            ->setLoginTypeArr()
            ->getDict();

        $data['dict'] = $dict;

        return self::success($data);
    }

    public function list($request)
    {
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

        $resList = $this->usersLoginLogDep->list($param);
        
        // === 优化：批量预加载用户数据 ===
        $userIds = $resList->pluck('user_id')->filter()->unique()->toArray();
        $userMap = $this->usersDep->getMap($userIds);

        $data['list'] = $resList->map(function ($item) use ($userMap) {
            // 使用预加载的Map获取用户
            $username = 'Unknown';
            if (!empty($item['user_id'])) {
                $resUser = $userMap->get($item['user_id']);
                if ($resUser) {
                    $username = $resUser->username;
                }
            }
            
            return [
                'id' => $item['id'],
                'user_name' => $username,
                'login_account' => $item['login_account'],
                'login_type' => $item['login_type'],
                'login_type_name' => SystemEnum::$loginTypeArr[$item['login_type']] ?? '',
                'platform' => $item['platform'],
                'ip' => $item['ip'],
                'ua' => $item['ua'],
                'is_success' => $item['is_success'],
                'reason' => $item['reason'] ?? '',
                'created_at' => $item['created_at']->toDateTimeString(),
            ];
        });
        
        $data['page'] = [
            'page_size' => (int)$param['page_size'],
            'current_page' => (int)$param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];

        return self::paginate($data['list'], $data['page']);
    }
}

<?php

namespace app\module\User;

use app\dep\AddressDep;
use app\dep\User\RoleDep;
use app\dep\User\UsersDep;
use app\dep\User\UserSessionsDep;
use app\dep\User\UserProfileDep;
use app\enum\CommonEnum;
use app\enum\SexEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\ExportService;
use Carbon\Carbon;
use support\Cache;
use support\Redis;
use app\validate\User\UsersListValidate;

class UsersListModule extends BaseModule
{
    public $UserDep;
    public $UserSessionsDep;
    public $RoleDep;
    public $AddressDep;
    public $UserProfileDep;

    public function __construct()
    {
        $this->UserDep = new UsersDep();
        $this->UserSessionsDep = new UserSessionsDep();
        $this->RoleDep = new RoleDep();
        $this->AddressDep = new AddressDep();
        $this->UserProfileDep = new UserProfileDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $dict = $dictService
            ->setRoleArr()
            ->setAuthAdressTree()
            ->setSexArr()
            ->setPlatformArr()
            ->getDict();
        $data['dict'] = $dict;
        return self::response($data);
    }

    public function edit($request)
    {
        try {
            $param = $this->validate($request, UsersListValidate::edit());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }
        $userDep = $this->UserDep;
        $profileDep = $this->UserProfileDep;

        $userData = [
            'username' => $param['username'],
            'role_id' => $param['role_id'],
        ];
        $profileData = [
            'avatar' => $param['avatar'] ?? null,
            'sex' => (int)$param['sex'],
            'address_id' => (int)$param['address'],
            'detail_address' => $param['detail_address'] ?? '',
            'bio' => $param['bio'] ?? '',
        ];
        $userDep->edit($param['id'], $userData);
        $profileDep->editByUserId($param['id'], $profileData);
        
        // Clear permission cache
        Cache::delete('auth_perm_uid_' . $param['id']);

        return self::response();
    }

    public function del($request)
    {
        try {
            $param = $this->validate($request, UsersListValidate::del());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }
        $this->UserDep->del($param['id'], ['is_del' => CommonEnum::YES]);
        return self::response();
    }

    public function list($request)
    {
        $dep = $this->UserDep;
        $RoleDep = $this->RoleDep;
        $AddressDep = $this->AddressDep;
        $UserSessionsDep = $this->UserSessionsDep;
        $UserProfileDep = $this->UserProfileDep;
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;
        $platform = $param['platform'] ?? 'admin';

        $resList = $dep->list($param);
        $data['list'] = $resList->map(function ($item) use ($RoleDep, $AddressDep, $UserSessionsDep, $UserProfileDep, $platform) {
            $resRole = $RoleDep->first($item->role_id);
            $resUserSession = $UserSessionsDep->firstLatestActiveByUserPlatform($item->id, $platform);
            $profile = $UserProfileDep->firstByUserId($item->id);
            $districtId = (int)($profile->address_id ?? 0);
            $addressParts = [];
            if ($districtId) {
                $node = $AddressDep->first($districtId);
                while ($node) {
                    array_unshift($addressParts, $node->name);
                    if ($node->parent_id === -1) break;
                    $node = $AddressDep->first($node->parent_id);
                }
            }
            $detail = $profile->detail_address ?? '';
            $address = implode('-', $addressParts);
            $address = $address ? ($address . '-' . $detail) : $detail;

            $expiresAt = $resUserSession->expires_at ?? null;
            $isExpired = '无记录';
            if ($expiresAt) {
                $isExpired = $expiresAt < Carbon::now()->toDateTimeString() ? '已过期' : '未过期';
            }

            return [
                'id' => $item->id,
                'uid' => $item->uid,
                'username' => $item->username,
                'email' => $item->email,
                'avatar' => $profile->avatar ?? null,
                'phone' => $item->phone,
                'sex' => (int)($profile->sex ?? 1),
                'sex_show' => SexEnum::$SexArr[(int)($profile->sex ?? 1)],
                'role_id' => $item->role_id,
                'bio' => $profile->bio ?? '',
                'role_name' => $resRole->name,
                'address_show' => $address,
                'address' => (int)($profile->address_id ?? 0),
                'detail_address' => $profile->detail_address ?? '',
                'expires_in' => $expiresAt,
                'is_expired' => $isExpired,
                'ip' => $resUserSession->ip ?? '',
                'platform' => $resUserSession->platform ?? '',
                'device_id' => $resUserSession->device_id ?? '',
                'last_seen_at' => $resUserSession->last_seen_at ?? '',
                'created_at' => $item['created_at']->toDateTimeString(),
            ];
        });
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];
        return self::response($data);
    }

    public function batchEdit($request)
    {
        try {
            $param = $this->validate($request, UsersListValidate::batchEdit());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }
        $profileDep = $this->UserProfileDep;
        $id = $param['ids'];
        if ($param['field'] == 'sex') {
            if (empty($param['sex'])) {
                return self::error('性别不能为空');
            }
            $profileDep->editByUserId($id, ['sex' => (int)$param['sex']]);
        }
        if ($param['field'] == 'address') {
            if (empty($param['address'])) {
                return self::error('地址不能为空');
            }
            $profileDep->editByUserId($id, ['address_id' => (int)$param['address']]);
        }
        if ($param['field'] == 'detail_address') {
            if (empty($param['detail_address'])) {
                return self::error('详细地址不能为空');
            }
            $profileDep->editByUserId($id, ['detail_address' => $param['detail_address']]);
        }
        return self::response();
    }

    public function export($request)
    {
        try {
            $param = $this->validate($request, UsersListValidate::export());
        } catch (\RuntimeException $e) {
            return self::error($e->getMessage());
        }
        $users = $this->UserDep->getUsersByIds($param['ids']);
        $roleDep = $this->RoleDep;
        $profileDep = $this->UserProfileDep;
        $headers = [
            'id' => '用户ID',
            'username' => '用户名',
            'email' => '邮箱',
            'phone' => '手机号',
            'avatar' => '头像',
            'sex' => '性别',
            'role' => '角色',
        ];
        $data = $users->map(function ($item) use ($roleDep, $profileDep) {
            $resRole = $roleDep->first($item->role_id);
            $profile = $profileDep->firstByUserId($item->id);
            return [
                'id' => $item->id,
                'username' => $item->username,
                'email' => $item->email,
                'phone' => $item->phone,
                'avatar' => $profile->avatar ?? null,
                'sex' => SexEnum::$SexArr[(int)($profile->sex ?? 1)],
                'role' => $resRole['name'],
            ];
        })->toArray();
        $exportService = new ExportService();
        $url = $exportService->export($headers, $data, 'users_export');
        return self::response(['url' => $url]);
    }

    public function kick($request)
    {
        $id = $request->post('id');
        $platform = $request->post('platform');
        
        if (!$id) return self::error('用户ID不能为空');
        if (!$platform) return self::error('平台不能为空');

        $sessionDep = $this->UserSessionsDep;
        
        // 1. 获取该用户在该平台下的最新有效会话
        $session = $sessionDep->firstLatestActiveByUserPlatform($id, $platform);
        
        if (!$session) {
            return self::error('该用户当前未在线或无有效会话');
        }

        // 2. 移除 Redis 指针
        $curSessKey = "cur_sess:" . strtolower(trim($platform)) . ":{$id}";
        Redis::connection('token')->del($curSessKey);

        // 3. 移除 Access Token 缓存 (让其立即失效)
        if (!empty($session->access_token_hash)) {
            Redis::connection('token')->del($session->access_token_hash);
        }

        // 4. 数据库标记为已撤销
        $sessionDep->revokeById($session->id);

        return self::response([], '用户已踢下线');
    }
}


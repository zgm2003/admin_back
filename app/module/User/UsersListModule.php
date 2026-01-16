<?php

namespace app\module\User;

use app\dep\AddressDep;
use app\dep\Permission\RoleDep;
use app\dep\User\UsersDep;
use app\dep\User\UserProfileDep;
use app\enum\SexEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\service\ExportService;
use support\Cache;
use app\validate\User\UsersListValidate;

class UsersListModule extends BaseModule
{
    protected UsersDep $usersDep;
    protected RoleDep $roleDep;
    protected AddressDep $addressDep;
    protected UserProfileDep $userProfileDep;

    public function __construct()
    {
        $this->usersDep = new UsersDep();
        $this->roleDep = new RoleDep();
        $this->addressDep = new AddressDep();
        $this->userProfileDep = new UserProfileDep();
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
        return self::success($data);
    }

    public function edit($request)
    {
        $param = $this->validate($request, UsersListValidate::edit());

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
        $this->usersDep->update($param['id'], $userData);
        $this->userProfileDep->updateByUserId($param['id'], $profileData);
        
        // Clear permission cache
        Cache::delete('auth_perm_uid_' . $param['id']);

        return self::success();
    }

    public function del($request)
    {
        $param = $this->validate($request, UsersListValidate::del());
        $this->usersDep->delete($param['id']);
        return self::success();
    }

    public function list($request)
    {
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

        $resList = $this->usersDep->list($param);
        
        // 批量预加载所有关联数据
        $userIds = $resList->pluck('id')->toArray();
        $roleIds = $resList->pluck('role_id')->unique()->toArray();
        
        // 批量查询，返回Map (key => model)
        $roleMap = $this->roleDep->getMap($roleIds);
        $profileMap = $this->userProfileDep->getMapByUserIds($userIds);
        
        $data['list'] = $resList->map(function ($item) use ($roleMap, $profileMap) {
            $resRole = $roleMap->get($item->role_id);
            $profile = $profileMap->get($item->id);
            
            // 地址路径构建（使用内存缓存）
            $districtId = (int)($profile->address_id ?? 0);
            $addressPath = $this->addressDep->buildAddressPath($districtId);
            $detail = $profile->detail_address ?? '';
            $address = $addressPath ? ($addressPath . '-' . $detail) : $detail;

            return [
                'id' => $item->id,
                'username' => $item->username,
                'email' => $item->email,
                'avatar' => $profile->avatar ?? null,
                'phone' => $item->phone,
                'sex' => (int)($profile->sex ?? 1),
                'sex_show' => SexEnum::$SexArr[(int)($profile->sex ?? 1)],
                'role_id' => $item->role_id,
                'role_name' => $resRole->name ?? '',
                'bio' => $profile->bio ?? '',
                'address_show' => $address,
                'address' => $districtId,
                'detail_address' => $profile->detail_address ?? '',
                'created_at' => $item['created_at']->toDateTimeString(),
            ];
        });
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];
        return self::success($data);
    }

    public function batchEdit($request)
    {
        $param = $this->validate($request, UsersListValidate::batchEdit());
        $id = $param['ids'];
        if ($param['field'] == 'sex') {
            self::throwIf(empty($param['sex']), '性别不能为空');
            $this->userProfileDep->updateByUserId($id, ['sex' => (int)$param['sex']]);
        }
        if ($param['field'] == 'address') {
            self::throwIf(empty($param['address']), '地址不能为空');
            $this->userProfileDep->updateByUserId($id, ['address_id' => (int)$param['address']]);
        }
        if ($param['field'] == 'detail_address') {
            self::throwIf(empty($param['detail_address']), '详细地址不能为空');
            $this->userProfileDep->updateByUserId($id, ['detail_address' => $param['detail_address']]);
        }
        return self::success();
    }

    public function export($request)
    {
        $param = $this->validate($request, UsersListValidate::export());
        $users = $this->usersDep->getMap($param['ids'])->values();
        
        // 批量预加载
        $userIds = $users->pluck('id')->toArray();
        $roleIds = $users->pluck('role_id')->unique()->toArray();
        $roleMap = $this->roleDep->getMap($roleIds);
        $profileMap = $this->userProfileDep->getMapByUserIds($userIds);
        
        $headers = [
            'id' => '用户ID',
            'username' => '用户名',
            'email' => '邮箱',
            'phone' => '手机号',
            'avatar' => '头像',
            'sex' => '性别',
            'role' => '角色',
        ];
        $data = $users->map(function ($item) use ($roleMap, $profileMap) {
            $resRole = $roleMap->get($item->role_id);
            $profile = $profileMap->get($item->id);
            return [
                'id' => $item->id,
                'username' => $item->username,
                'email' => $item->email,
                'phone' => $item->phone,
                'avatar' => $profile->avatar ?? null,
                'sex' => SexEnum::$SexArr[(int)($profile->sex ?? 1)],
                'role' => $resRole->name ?? '',
            ];
        })->toArray();
        $exportService = new ExportService();
        $url = $exportService->export($headers, $data, 'users_export');
        return self::success(['url' => $url]);
    }
}

<?php

namespace app\module\User;

use app\dep\DevTools\ExportTaskDep;
use app\dep\Permission\RoleDep;
use app\dep\User\UsersDep;
use app\dep\User\UserProfileDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\AddressService;
use app\service\DictService;
use support\Cache;
use app\validate\User\UsersListValidate;
use Webman\RedisQueue\Client as RedisQueue;

class UsersListModule extends BaseModule
{
    protected UsersDep $usersDep;
    protected RoleDep $roleDep;
    protected UserProfileDep $userProfileDep;
    protected ExportTaskDep $exportTaskDep;
    protected AddressService $addressService;
    protected DictService $dictService;

    public function __construct()
    {
        $this->usersDep = $this->dep(UsersDep::class);
        $this->roleDep = $this->dep(RoleDep::class);
        $this->userProfileDep = $this->dep(UserProfileDep::class);
        $this->exportTaskDep = $this->dep(ExportTaskDep::class);
        $this->addressService = $this->svc(AddressService::class);
        $this->dictService = $this->svc(DictService::class);
    }

    public function init($request)
    {
        $dict = $this->dictService
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
        $param = $this->validate($request, UsersListValidate::list());
        $resList = $this->usersDep->list($param);
        
        // 批量预加载角色数据（profile 已在 Dep 层 JOIN 查出）
        $roleIds = $resList->pluck('role_id')->unique()->toArray();
        $roleMap = $this->roleDep->getMap($roleIds);
        
        $data['list'] = $resList->map(function ($item) use ($roleMap) {
            $resRole = $roleMap->get($item->role_id);
            
            // 地址路径构建（使用 AddressService）
            $districtId = (int)($item->address_id ?? 0);
            $addressPath = $this->addressService->buildAddressPath($districtId);
            $detail = $item->detail_address ?? '';
            $address = $addressPath ? ($addressPath . '-' . $detail) : $detail;

            return [
                'id' => $item->id,
                'username' => $item->username,
                'email' => $item->email,
                'avatar' => $item->avatar ?? null,
                'phone' => $item->phone,
                'sex' => (int)($item->sex ?? CommonEnum::SEX_UNKNOWN),
                'sex_show' => CommonEnum::$sexArr[(int)($item->sex ?? CommonEnum::SEX_UNKNOWN)],
                'role_id' => $item->role_id,
                'role_name' => $resRole->name ?? '',
                'bio' => $item->bio ?? '',
                'address_show' => $address,
                'address' => $districtId,
                'detail_address' => $item->detail_address ?? '',
                'created_at' => $item['created_at'],
            ];
        });
        $data['page'] = [
            'page_size' => $resList->perPage(),
            'current_page' => $resList->currentPage(),
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
                'sex' => CommonEnum::$sexArr[(int)($profile->sex ?? CommonEnum::SEX_UNKNOWN)],
                'role' => $resRole->name ?? '',
            ];
        })->toArray();

        // Module 层编排：写表 + 入队列
        $taskId = $this->exportTaskDep->create($request->userId, '用户列表导出');
        RedisQueue::send('export_task', [
            'task_id' => $taskId,
            'user_id' => $request->userId,
            'platform' => $request->platform ?? 'admin', // 推送到发起导出的平台
            'headers' => $headers,
            'data' => $data,
            'title' => '用户列表导出',
            'prefix' => 'users_export',
        ]);

        return self::success(['message' => '导出任务已提交，完成后将通知您']);
    }
}

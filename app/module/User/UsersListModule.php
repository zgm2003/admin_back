<?php

namespace app\module\User;

use app\dep\System\ExportTaskDep;
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

/**
 * 用户列表模块
 * 负责：用户列表初始化、列表查询、编辑、删除、批量编辑、导出
 */
class UsersListModule extends BaseModule
{
    // ==================== 公开接口 ====================

    /**
     * 初始化（获取字典数据）
     */
    public function init($request): array
    {
        $dict = $this->svc(DictService::class)
            ->setRoleArr()
            ->setAuthAdressTree()
            ->setSexArr()
            ->setPlatformArr()
            ->getDict();

        return self::success(['dict' => $dict]);
    }

    /**
     * 用户列表
     */
    public function list($request): array
    {
        $param = $this->validate($request, UsersListValidate::list());
        $paginator = $this->dep(UsersDep::class)->list($param);

        // 批量预加载角色数据（profile 已在 Dep 层 JOIN 查出）
        $roleIds = $paginator->pluck('role_id')->unique()->toArray();
        $roleMap = $this->dep(RoleDep::class)->getMap($roleIds);

        $list = $paginator->map(function ($item) use ($roleMap) {
            return $this->formatUserItem($item, $roleMap);
        });

        $page = [
            'page_size'    => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'total_page'   => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 编辑用户
     */
    public function edit($request): array
    {
        $param = $this->validate($request, UsersListValidate::edit());

        $this->dep(UsersDep::class)->update($param['id'], [
            'username' => $param['username'],
            'role_id'  => $param['role_id'],
        ]);

        $this->dep(UserProfileDep::class)->updateByUserId($param['id'], [
            'avatar'         => $param['avatar'] ?? null,
            'sex'            => (int)$param['sex'],
            'address_id'     => (int)$param['address'],
            'detail_address' => $param['detail_address'] ?? '',
            'bio'            => $param['bio'] ?? '',
        ]);

        // 清除权限缓存
        Cache::delete('auth_perm_uid_' . $param['id']);

        return self::success();
    }

    /**
     * 删除用户
     */
    public function del($request): array
    {
        $param = $this->validate($request, UsersListValidate::del());
        $this->dep(UsersDep::class)->delete($param['id']);
        return self::success();
    }

    /**
     * 批量编辑
     */
    public function batchEdit($request): array
    {
        $param = $this->validate($request, UsersListValidate::batchEdit());

        $ids = $param['ids'];
        $field = $param['field'];

        $updateData = match ($field) {
            'sex'            => ['sex' => (int)$param['sex']],
            'address'        => ['address_id' => (int)$param['address']],
            'detail_address' => ['detail_address' => $param['detail_address'] ?? ''],
            default          => null,
        };
        self::throwUnless($updateData, '不支持的批量编辑字段');

        // 校验值非空
        $valueMap = ['sex' => '性别', 'address' => '地址', 'detail_address' => '详细地址'];
        self::throwIf(empty($param[$field]), ($valueMap[$field] ?? $field) . '不能为空');

        $this->dep(UserProfileDep::class)->updateByUserIds($ids, $updateData);

        return self::success();
    }

    /**
     * 导出用户
     */
    public function export($request): array
    {
        $param = $this->validate($request, UsersListValidate::export());
        $users = $this->dep(UsersDep::class)->getMap($param['ids'])->values();

        // 批量预加载
        $roleIds = $users->pluck('role_id')->unique()->toArray();
        $roleMap = $this->dep(RoleDep::class)->getMap($roleIds);
        $profileMap = $this->dep(UserProfileDep::class)->getMapByUserIds(
            $users->pluck('id')->toArray()
        );

        $data = $users->map(fn($item) => $this->formatExportItem($item, $roleMap, $profileMap))->toArray();

        // 写表 + 入队列
        $taskId = $this->dep(ExportTaskDep::class)->create($request->userId, '用户列表导出');
        RedisQueue::send('export_task', [
            'task_id'  => $taskId,
            'user_id'  => $request->userId,
            'platform' => $request->platform ?? 'admin',
            'headers'  => self::EXPORT_HEADERS,
            'data'     => $data,
            'title'    => '用户列表导出',
            'prefix'   => 'users_export',
        ]);

        return self::success(['message' => '导出任务已提交，完成后将通知您']);
    }

    // ==================== 私有方法 ====================

    /** 导出表头 */
    private const EXPORT_HEADERS = [
        'id'       => '用户ID',
        'username' => '用户名',
        'email'    => '邮箱',
        'phone'    => '手机号',
        'avatar'   => '头像',
        'sex'      => '性别',
        'role'     => '角色',
    ];

    /**
     * 格式化列表单条用户数据
     */
    private function formatUserItem($item, $roleMap): array
    {
        $role = $roleMap->get($item->role_id);
        $districtId = (int)($item->address_id ?? 0);
        $addressPath = AddressService::buildAddressPath($districtId);
        $detail = $item->detail_address ?? '';

        return [
            'id'             => $item->id,
            'username'       => $item->username,
            'email'          => $item->email,
            'avatar'         => $item->avatar ?? null,
            'phone'          => $item->phone,
            'sex'            => (int)($item->sex ?? CommonEnum::SEX_UNKNOWN),
            'sex_show'       => CommonEnum::$sexArr[(int)($item->sex ?? CommonEnum::SEX_UNKNOWN)],
            'role_id'        => $item->role_id,
            'role_name'      => $role->name ?? '',
            'bio'            => $item->bio ?? '',
            'address_show'   => $addressPath ? ($addressPath . '-' . $detail) : $detail,
            'address'        => $districtId,
            'detail_address' => $item->detail_address ?? '',
            'created_at'     => $item['created_at'],
        ];
    }

    /**
     * 格式化导出单条数据
     */
    private function formatExportItem($item, $roleMap, $profileMap): array
    {
        $profile = $profileMap->get($item->id);
        return [
            'id'       => $item->id,
            'username' => $item->username,
            'email'    => $item->email,
            'phone'    => $item->phone,
            'avatar'   => $profile->avatar ?? null,
            'sex'      => CommonEnum::$sexArr[(int)($profile->sex ?? CommonEnum::SEX_UNKNOWN)],
            'role'     => $roleMap->get($item->role_id)->name ?? '',
        ];
    }
}

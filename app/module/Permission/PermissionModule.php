<?php

namespace app\module\Permission;

use app\dep\Permission\PermissionDep;
use app\dep\Permission\RolePermissionDep;
use app\dep\User\UsersDep;
use app\enum\PermissionEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\service\Permission\AuthPlatformService;
use app\service\User\PermissionService;
use app\validate\Permission\PermissionValidate;
use support\Redis;

/**
 * 权限管理模块
 * 负责：菜单/页面/按钮权限的 CRUD、状态切换、APP 端按钮权限管理
 * 权限类型：目录(DIR) / 页面(PAGE) / 按钮(BUTTON)
 * 修改后自动清理权限树缓存 + 用户权限缓存
 */
class PermissionModule extends BaseModule
{
    /**
     * 初始化（返回权限树、权限类型、平台字典）
     */
    public function init($request)
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setPermissionTree()
            ->setPermissionTypeArr()
            ->setPermissionPlatformArr()
            ->getDict();

        return self::success($data);
    }

    /**
     * 新增权限节点
     * 按类型分别校验必填字段和唯一性：
     * - 目录(DIR)：校验 i18n_key 唯一
     * - 页面(PAGE)：校验 path + i18n_key 唯一
     * - 按钮(BUTTON)：校验 code 唯一，admin 平台必须有父级
     */
    public function add($request)
    {
        $param = $this->validate($request, PermissionValidate::addBase());
        $param = $this->validate($request, PermissionValidate::add((int)$param['type'], $param['platform'] === 'admin'), $param);

        $platform = $param['platform'];
        $parentId = $this->normalizeParentId($param['parent_id'] ?? null);
        $dep = $this->dep(PermissionDep::class);

        $this->assertValidParentAssignment((int)$param['type'], $parentId, $platform);

        if ($param['type'] == PermissionEnum::TYPE_DIR) {
            self::throwIf($dep->existsByPlatformI18nKey($platform, $param['i18n_key']), '该平台下 i18n_key 已存在');

            $id = $dep->add([
                'name'      => $param['name'],
                'parent_id' => $parentId,
                'icon'      => $param['icon'] ?? '',
                'type'      => $param['type'],
                'platform'  => $platform,
                'i18n_key'  => $param['i18n_key'],
                'sort'      => $param['sort'],
                'show_menu' => $param['show_menu'],
            ]);
        } elseif ($param['type'] == PermissionEnum::TYPE_PAGE) {
            self::throwIf($dep->existsByPlatformPath($platform, $param['path']), '该平台下路由 path 已存在');
            self::throwIf($dep->existsByPlatformI18nKey($platform, $param['i18n_key']), '该平台下 i18n_key 已存在');

            $id = $dep->add([
                'name'      => $param['name'],
                'parent_id' => $parentId,
                'path'      => $param['path'],
                'component' => $param['component'],
                'type'      => $param['type'],
                'platform'  => $platform,
                'icon'      => $param['icon'] ?? '',
                'i18n_key'  => $param['i18n_key'],
                'sort'      => $param['sort'],
                'show_menu' => $param['show_menu'],
            ]);
        } elseif ($param['type'] == PermissionEnum::TYPE_BUTTON) {
            self::throwIf($dep->existsByPlatformCode($platform, $param['code']), '该平台下权限标识已存在');

            $id = $dep->add([
                'name'      => $param['name'],
                'parent_id' => $parentId,
                'code'      => $param['code'],
                'type'      => $param['type'],
                'platform'  => $platform,
                'sort'      => $param['sort'],
            ]);
        }

        $this->clearPermissionCache();
        $this->clearUserPermissionCacheByPermissionIds([$id]);

        return self::success();
    }

    /**
     * 编辑权限节点
     * 按类型分别校验必填字段和唯一性（排除自身）
     */
    public function edit($request)
    {
        $param = $this->validate($request, PermissionValidate::editBase());
        $requiresButtonParent = (int)$param['type'] === PermissionEnum::TYPE_BUTTON && $param['platform'] === 'admin';
        $param = $this->validate($request, PermissionValidate::edit((int)$param['type'], $requiresButtonParent), $param);

        $platform = $param['platform'];
        $parentId = $this->normalizeParentId($param['parent_id'] ?? null);
        $id = $param['id'];
        $dep = $this->dep(PermissionDep::class);

        $this->assertValidParentAssignment((int)$param['type'], $parentId, $platform, (int)$id);
        $this->assertExistingChildrenCompatible((int)$param['type'], (int)$id);

        if ($param['type'] == PermissionEnum::TYPE_DIR) {
            self::throwIf($dep->existsByPlatformI18nKey($platform, $param['i18n_key'], $id), '该平台下 i18n_key 已存在');

            $dep->update($id, [
                'name'      => $param['name'],
                'parent_id' => $parentId,
                'icon'      => $param['icon'] ?? '',
                'type'      => $param['type'],
                'platform'  => $platform,
                'i18n_key'  => $param['i18n_key'],
                'sort'      => $param['sort'],
                'show_menu' => $param['show_menu'],
            ]);
        } elseif ($param['type'] == PermissionEnum::TYPE_PAGE) {
            self::throwIf($dep->existsByPlatformPath($platform, $param['path'], $id), '该平台下路由 path 已存在');
            self::throwIf($dep->existsByPlatformI18nKey($platform, $param['i18n_key'], $id), '该平台下 i18n_key 已存在');

            $dep->update($id, [
                'name'      => $param['name'],
                'parent_id' => $parentId,
                'path'      => $param['path'],
                'component' => $param['component'],
                'type'      => $param['type'],
                'platform'  => $platform,
                'icon'      => $param['icon'] ?? '',
                'i18n_key'  => $param['i18n_key'],
                'sort'      => $param['sort'],
                'show_menu' => $param['show_menu'],
            ]);
        } elseif ($param['type'] == PermissionEnum::TYPE_BUTTON) {
            self::throwIf($dep->existsByPlatformCode($platform, $param['code'], $id), '该平台下权限标识已存在');

            $dep->update($id, [
                'name'      => $param['name'],
                'parent_id' => $parentId,
                'code'      => $param['code'],
                'type'      => $param['type'],
                'platform'  => $platform,
                'sort'      => $param['sort'] ?? 1,
            ]);
        }

        $this->clearPermissionCache();
        $this->clearUserPermissionCacheByPermissionIds([$id]);

        return self::success();
    }

    /**
     * 删除权限节点（支持批量，删除后清理缓存）
     */
    public function del($request)
    {
        $param = $this->validate($request, PermissionValidate::del());
        $ids = \is_array($param['id']) ? array_map('intval', $param['id']) : [(int)$param['id']];

        $dep = $this->permissionDep();
        self::throwIf($dep->hasChildrenOutsideIds($ids), '存在子节点未被勾选，不能删除');
        $this->clearUserPermissionCacheByPermissionIds($ids);

        $dep->delete($ids);

        $this->clearPermissionCache();

        return self::success();
    }

    protected function clearPermissionCache(): void
    {
        PermissionDep::clearCache();
        DictService::clearPermissionCache();
    }

    protected function assertValidParentAssignment(int $type, int $parentId, string $platform, ?int $currentId = null): void
    {
        $dep = $this->dep(PermissionDep::class);

        if ($currentId !== null) {
            self::throwIf($parentId === $currentId, '节点不能选择自己作为父级');

            $descendantIds = array_values(array_diff($dep->getCascadeIds([$currentId]), [$currentId]));
            self::throwIf(in_array($parentId, $descendantIds, true), '节点不能挂到自己的后代下面');
        }

        if ($parentId === PermissionEnum::ROOT_PARENT_ID) {
            self::throwIf($type === PermissionEnum::TYPE_BUTTON && $platform === 'admin', '按钮类型的父节点只能是页面');
            return;
        }

        $parent = $dep->get($parentId);
        self::throwNotFound($parent, '父节点不存在');
        self::throwIf(($parent->platform ?? '') !== $platform, '父节点与当前平台不一致');

        $parentType = (int)$parent->type;
        if ($type === PermissionEnum::TYPE_DIR) {
            self::throwIf(!in_array($parentType, [PermissionEnum::TYPE_DIR], true), '目录类型的父节点只能是目录或根节点');
            return;
        }

        if ($type === PermissionEnum::TYPE_PAGE) {
            self::throwIf($parentType !== PermissionEnum::TYPE_DIR, '页面类型的父节点只能是目录或根节点');
            return;
        }

        if ($type === PermissionEnum::TYPE_BUTTON) {
            self::throwIf($parentType !== PermissionEnum::TYPE_PAGE, '按钮类型的父节点只能是页面');
        }
    }

    protected function assertExistingChildrenCompatible(int $type, int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $children = $this->dep(PermissionDep::class)->getActiveChildrenByParentId($id);
        if ($children->isEmpty()) {
            return;
        }

        if ($type === PermissionEnum::TYPE_BUTTON) {
            self::throw('按钮类型不能包含子节点');
        }

        $childTypes = $children->pluck('type')->map(static fn($childType) => (int)$childType)->unique()->values()->toArray();
        if ($type === PermissionEnum::TYPE_PAGE) {
            self::throwIf(
                count(array_diff($childTypes, [PermissionEnum::TYPE_BUTTON])) > 0,
                '页面类型的子节点只能是按钮'
            );
            return;
        }

        if ($type === PermissionEnum::TYPE_DIR) {
            self::throwIf(
                in_array(PermissionEnum::TYPE_BUTTON, $childTypes, true),
                '目录类型的子节点不能是按钮'
            );
        }
    }

    protected function normalizeParentId(mixed $parentId): int
    {
        if ($parentId === null || $parentId === '') {
            return PermissionEnum::ROOT_PARENT_ID;
        }

        return (int)$parentId;
    }

    protected function permissionDep()
    {
        return $this->dep(PermissionDep::class);
    }

    /**
     * 批量编辑（目前仅支持 description 字段）
     */
    public function batchEdit($request)
    {
        $param = $this->validate($request, PermissionValidate::batchEdit());
        $ids = \is_array($param['ids']) ? $param['ids'] : [$param['ids']];

        if ($param['field'] == 'description') {
            self::throw('当前批量编辑不支持 description 字段');
        }

        self::throw('不支持的批量编辑字段');
    }

    /**
     * 权限列表（树形结构，按平台过滤）
     */
    public function list($request)
    {
        $param = $this->validate($request, PermissionValidate::list());
        $resList = $this->dep(PermissionDep::class)->list($param);

        $data['list'] = $resList->map(fn($item) => $this->mapPermissionListItem($item));

        $hasFilter = !empty($param['name']) || !empty($param['path']) || !empty($param['type']);
        if ($hasFilter) {
            $allList = $this->dep(PermissionDep::class)
                ->list(['platform' => $param['platform']])
                ->map(fn($item) => $this->mapPermissionListItem($item))
                ->toArray();
            $matchedIds = array_map(
                static fn(array $item): int => (int)$item['id'],
                $data['list']->toArray()
            );

            $data['menu_tree'] = PermissionService::buildTreeWithMatchedAncestors($allList, $matchedIds);
        } else {
            $data['menu_tree'] = listToTree($data['list']->toArray(), PermissionEnum::ROOT_PARENT_ID);
        }

        return self::success($data['menu_tree']);
    }

    protected function mapPermissionListItem($item): array
    {
        return [
            'id'        => $item->id,
            'name'      => $item->name,
            'path'      => $item->path,
            'parent_id' => (int)$item->parent_id,
            'icon'      => $item->icon,
            'component' => $item->component,
            'status'    => $item->status,
            'type'      => $item->type,
            'type_name' => PermissionEnum::$typeArr[$item->type],
            'code'      => $item->code,
            'i18n_key'  => $item->i18n_key,
            'sort'      => $item->sort,
            'show_menu' => $item->show_menu,
        ];
    }

    /**
     * APP/H5/小程序 按钮权限列表（扁平化，非树形）
     */
    public function appButtonList($request)
    {
        $param = $this->validate($request, PermissionValidate::list());
        $param['type'] = PermissionEnum::TYPE_BUTTON;

        $resList = $this->dep(PermissionDep::class)->list($param);

        $data = $resList->map(fn($item) => [
            'id'            => $item->id,
            'name'          => $item->name,
            'status'        => $item->status,
            'code'          => $item->code,
            'sort'          => $item->sort,
            'platform'      => $item->platform,
            'platform_name' => AuthPlatformService::getPlatformName($item->platform),
        ]);

        return self::success($data->toArray());
    }

    /**
     * APP/H5/小程序 按钮权限新增（仅限非 admin 平台）
     */
    public function appButtonAdd($request)
    {
        $param = $this->validate($request, PermissionValidate::appButtonAdd());
        $platform = $param['platform'];
        $dep = $this->dep(PermissionDep::class);

        self::throwIf($platform === 'admin', '仅限非 PC 端平台操作');
        self::throwIf($dep->existsByPlatformCode($platform, $param['code']), '该平台下权限标识已存在');

        $id = $dep->add([
            'name'      => $param['name'],
            'parent_id' => PermissionEnum::ROOT_PARENT_ID,
            'code'      => $param['code'],
            'type'      => PermissionEnum::TYPE_BUTTON,
            'platform'  => $platform,
            'sort'      => $param['sort'] ?? 1,
        ]);

        $this->clearPermissionCache();
        $this->clearUserPermissionCacheByPermissionIds([$id]);

        return self::success();
    }

    /**
     * APP/H5/小程序 按钮权限编辑（仅限非 admin 平台，排除自身唯一校验）
     */
    public function appButtonEdit($request)
    {
        $param = $this->validate($request, PermissionValidate::appButtonEdit());
        $platform = $param['platform'];
        $id = $param['id'];
        $dep = $this->dep(PermissionDep::class);

        self::throwIf($platform === 'admin', '仅限非 PC 端平台操作');
        self::throwIf($dep->existsByPlatformCode($platform, $param['code'], $id), '该平台下权限标识已存在');

        $dep->update($id, [
            'name'      => $param['name'],
            'parent_id' => PermissionEnum::ROOT_PARENT_ID,
            'code'      => $param['code'],
            'type'      => PermissionEnum::TYPE_BUTTON,
            'platform'  => $platform,
            'sort'      => $param['sort'] ?? 1,
        ]);

        $this->clearPermissionCache();
        $this->clearUserPermissionCacheByPermissionIds([$id]);

        return self::success();
    }

    /**
     * 切换权限状态（启用/禁用，清理所有缓存 + 用户权限缓存）
     */
    public function status($request)
    {
        $param = $this->validate($request, PermissionValidate::status());
        $this->dep(PermissionDep::class)->update($param['id'], ['status' => $param['status']]);

        $this->clearPermissionCache();
        $this->clearUserPermissionCacheByPermissionIds([(int)$param['id']]);

        return self::success();
    }

    /**
     * 根据受影响权限，定向清理角色绑定用户的按钮权限缓存。
     */
    protected function clearUserPermissionCacheByPermissionIds(array $permissionIds): void
    {
        $permissionIds = $this->permissionDep()->getCascadeIds($permissionIds);
        if (empty($permissionIds)) {
            return;
        }

        $roleIds = $this->dep(RolePermissionDep::class)->getRoleIdsByPermissionIds($permissionIds);
        if (empty($roleIds)) {
            return;
        }

        $userIds = $this->dep(UsersDep::class)->getIdsByRoleIds($roleIds)->toArray();
        if (empty($userIds)) {
            return;
        }

        $keys = [];
        foreach ($userIds as $uid) {
            foreach (AuthPlatformService::getAllowedPlatforms() as $platform) {
                $keys[] = 'cache:' . PermissionService::buttonCacheKey((int)$uid, $platform);
            }
        }

        if (empty($keys)) {
            return;
        }

        $redis = Redis::connection('cache');
        foreach (array_chunk($keys, 500) as $chunk) {
            $redis->del(...$chunk);
        }
    }
}

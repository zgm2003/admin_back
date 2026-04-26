<?php

namespace app\service\User;

use app\dep\Permission\PermissionDep;
use app\dep\Permission\RoleDep;
use app\dep\Permission\RolePermissionDep;
use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\service\Permission\AuthPlatformService;

/**
 * 权限计算服务
 * 唯一事实源：根据用户角色 + 平台，计算菜单树、路由表、按钮权限码
 * 算法：叶子节点向上回溯 + 路径缓存，O(N) 构建树
 */
class PermissionService
{
    public const BUTTON_CACHE_KEY_VERSION = 'v20260426_remove_app_button_menu';

    private static ?RoleDep $roleDep = null;
    private static ?RolePermissionDep $rolePermissionDep = null;
    private static ?PermissionDep $permissionDep = null;

    private static function roleDep(): RoleDep
    {
        return self::$roleDep ??= new RoleDep();
    }

    private static function permDep(): PermissionDep
    {
        return self::$permissionDep ??= new PermissionDep();
    }

    private static function rolePermissionDep(): RolePermissionDep
    {
        return self::$rolePermissionDep ??= new RolePermissionDep();
    }

    /**
     * ⭐ 权限计算唯一事实源
     * 根据用户角色的叶子权限 ID，向上回溯构建完整权限树
     *
     * @param mixed  $user     用户对象（需含 role_id）
     * @param string $platform 平台标识（admin/app 等）
     * @return array{permissions: array, router: array, buttonCodes: array}
     */
    public static function buildPermissionContextByUser($user, string $platform): array
    {
        $roleId = (int)($user->role_id ?? 0);
        if ($roleId <= 0) {
            return ['permissions' => [], 'router' => [], 'buttonCodes' => []];
        }

        $role = self::roleDep()->find($roleId);
        if (!$role) {
            return ['permissions' => [], 'router' => [], 'buttonCodes' => []];
        }

        if (!\in_array($platform, AuthPlatformService::getAllowedPlatforms(), true)) {
            throw new \InvalidArgumentException("无效的平台标识: {$platform}");
        }

        $leafIds = self::normalizeLeafIds(self::rolePermissionDep()->getPermissionIdsByRoleId($roleId));

        if (empty($leafIds)) {
            return ['permissions' => [], 'router' => [], 'buttonCodes' => []];
        }

        // 1. 获取全量权限数据并按平台过滤（Dep 层已做缓存）
        $allPerms = self::permDep()->getAllPermissions();
        $allPerms = array_filter($allPerms, fn($p) => ($p['platform'] ?? '') === $platform);
        $permMap  = array_column($allPerms, null, 'id');

        // 2. 叶子节点向上回溯，计算有效权限 ID（路径缓存避免重复遍历）
        $enabledIdMap = self::resolveEnabledIds($leafIds, $permMap);
        $enabledIds   = array_keys($enabledIdMap);

        // 3. 按类型分类收集：菜单、路由、按钮
        $menusData   = [];
        $routerData  = [];
        $buttonCodes = [];

        foreach ($enabledIds as $id) {
            $p = $permMap[$id];

            // 按钮权限
            if ($p['type'] === PermissionEnum::TYPE_BUTTON && !empty($p['code'])) {
                $buttonCodes[] = $p['code'];
            }

            // 路由数据（仅页面类型）
            if ($p['type'] == PermissionEnum::TYPE_PAGE && !empty($p['path']) && !empty($p['component'])) {
                $routerData[] = self::buildRouteRecord($p);
            }

            // 菜单数据（目录 + 页面，前端需要全量数据处理面包屑和 Tab 显隐）
            if (\in_array($p['type'], [PermissionEnum::TYPE_DIR, PermissionEnum::TYPE_PAGE])) {
                $menusData[] = $p;
            }
        }

        // 按 sort 排序，确保菜单顺序正确
        usort($menusData, fn($a, $b) => $a['sort'] <=> $b['sort']);

        // 4. O(N) 构建菜单树
        return [
            'permissions' => self::buildPermissionTree($menusData),
            'router'      => $routerData,
            'buttonCodes' => array_values(array_unique($buttonCodes)),
        ];
    }

    public static function buildRouteViewKey(string $component): string
    {
        return ltrim($component, '/');
    }

    public static function buttonCacheKey(int $userId, string $platform): string
    {
        return 'auth_perm_uid_' . self::BUTTON_CACHE_KEY_VERSION . "_{$userId}_{$platform}";
    }

    /**
     * @param array{id:int|string,path:string,component:string} $permission
     * @return array{name:string,path:string,view_key:string,meta:array{menuId:string}}
     */
    public static function buildRouteRecord(array $permission): array
    {
        return [
            'name' => "menu_{$permission['id']}",
            'path' => $permission['path'],
            'view_key' => self::buildRouteViewKey((string) $permission['component']),
            'meta' => ['menuId' => (string) $permission['id']],
        ];
    }

    /**
     * 根据命中过滤条件的节点，补齐祖先链后再组树。
     *
     * @param array<int, array{id:int|string,parent_id:int|string}> $items
     * @param array<int, int|string> $matchedIds
     * @return array<int, array<string, mixed>>
     */
    public static function buildTreeWithMatchedAncestors(array $items, array $matchedIds): array
    {
        $matchedIds = self::normalizeLeafIds($matchedIds);
        if (empty($matchedIds) || empty($items)) {
            return [];
        }

        $itemMap = [];
        foreach ($items as $item) {
            $itemMap[(int)$item['id']] = $item;
        }

        $includedIdMap = [];
        foreach ($matchedIds as $matchedId) {
            $currentId = $matchedId;
            $visited = [];

            while (isset($itemMap[$currentId])) {
                if (isset($visited[$currentId])) {
                    break;
                }
                $visited[$currentId] = true;
                $includedIdMap[$currentId] = true;

                $parentId = (int)$itemMap[$currentId]['parent_id'];
                if ($parentId === PermissionEnum::ROOT_PARENT_ID) {
                    break;
                }

                $currentId = $parentId;
            }
        }

        $filteredItems = array_values(array_filter(
            $items,
            static fn(array $item): bool => isset($includedIdMap[(int)$item['id']])
        ));

        return self::buildRawTree($filteredItems);
    }

    // ==================== 私有方法 ====================

    /**
     * 叶子节点向上回溯，计算所有有效权限 ID
     * 路径缓存：已确认有效的节点直接跳过，避免重复遍历
     * 环检测：visited 防止数据异常导致死循环
     */
    private static function normalizeLeafIds(array $permissionIds): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $permissionIds),
            static fn(int $id) => $id > 0
        )));
    }

    private static function resolveEnabledIds(array $leafIds, array $permMap): array
    {
        $enabledIdMap = [];

        foreach ($leafIds as $leafId) {
            $curr = (int)$leafId;
            if (!isset($permMap[$curr])) {
                continue;
            }

            $path    = [];
            $isValid = false;
            $visited = [];

            while (isset($permMap[$curr])) {
                // 命中已确认有效的节点，当前路径全部有效
                if (isset($enabledIdMap[$curr])) {
                    $isValid = true;
                    break;
                }

                // 环检测
                if (isset($visited[$curr])) {
                    break;
                }
                $visited[$curr] = true;

                $path[] = $curr;
                $parentId = (int)$permMap[$curr]['parent_id'];

                // 到达根节点
                if ($parentId === PermissionEnum::ROOT_PARENT_ID) {
                    $isValid = true;
                    break;
                }
                $curr = $parentId;
            }

            if ($isValid) {
                foreach ($path as $id) {
                    $enabledIdMap[$id] = true;
                }
            }
        }

        return $enabledIdMap;
    }

    /**
     * O(N) 复杂度构建权限菜单树（两次遍历：初始化 map → 挂载子节点）
     */
    private static function buildPermissionTree(array $items): array
    {
        $tree = [];
        $map  = [];

        // 第一遍：初始化节点 map
        foreach ($items as $item) {
            $map[$item['id']] = [
                'index'     => (string)$item['id'],
                'label'     => $item['name'],
                'path'      => $item['path'],
                'icon'      => $item['icon'],
                'children'  => [],
                'i18n_key'  => $item['i18n_key'] ?? '',
                'sort'      => (int)$item['sort'],
                'show_menu' => isset($item['show_menu']) ? (int)$item['show_menu'] : CommonEnum::YES,
                'parent_id' => (int)$item['parent_id'],
            ];
        }

        // 第二遍：挂载到父节点或根
        foreach ($map as $id => &$node) {
            $parentId = $node['parent_id'];
            if ($parentId === PermissionEnum::ROOT_PARENT_ID) {
                $tree[] = &$node;
            } elseif (isset($map[$parentId])) {
                $map[$parentId]['children'][] = &$node;
            }
        }

        return $tree;
    }

    /**
     * 为权限管理页保留原始字段结构的树构建。
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private static function buildRawTree(array $items): array
    {
        $tree = [];
        $map = [];

        foreach ($items as $item) {
            $item['children'] = [];
            $map[(int)$item['id']] = $item;
        }

        foreach ($map as $id => &$node) {
            $parentId = (int)$node['parent_id'];
            if ($parentId === PermissionEnum::ROOT_PARENT_ID) {
                $tree[] = &$node;
                continue;
            }

            if (isset($map[$parentId])) {
                $map[$parentId]['children'][] = &$node;
            }
        }

        return $tree;
    }
}

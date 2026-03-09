<?php

namespace app\service\User;

use app\dep\Permission\PermissionDep;
use app\dep\Permission\RoleDep;
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
    private static ?RoleDep $roleDep = null;
    private static ?PermissionDep $permissionDep = null;

    private static function roleDep(): RoleDep
    {
        return self::$roleDep ??= new RoleDep();
    }

    private static function permDep(): PermissionDep
    {
        return self::$permissionDep ??= new PermissionDep();
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

        $leafIds = self::normalizeLeafIds($role->permission_id ?? []);

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
                $routerData[] = [
                    'name'      => "menu_{$p['id']}",
                    'path'      => $p['path'],
                    'component' => $p['component'],
                    'meta'      => ['menuId' => (string)$p['id']],
                ];
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

    // ==================== 私有方法 ====================

    /**
     * 叶子节点向上回溯，计算所有有效权限 ID
     * 路径缓存：已确认有效的节点直接跳过，避免重复遍历
     * 环检测：visited 防止数据异常导致死循环
     */
    private static function normalizeLeafIds(mixed $permissionIds): array
    {
        if (is_array($permissionIds)) {
            return array_values(array_unique(array_filter(
                array_map('intval', $permissionIds),
                static fn(int $id) => $id > 0
            )));
        }

        if (!is_string($permissionIds) || $permissionIds === '') {
            return [];
        }

        $decoded = json_decode($permissionIds, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $decoded),
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
}
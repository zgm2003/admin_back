<?php

namespace app\service\User;

use app\dep\Permission\PermissionDep;
use app\dep\Permission\RoleDep;
use app\enum\PermissionEnum;
use app\enum\CommonEnum;

class PermissionService
{
    protected $roleDep;
    protected $permissionDep;

    public function __construct()
    {
        $this->roleDep = new RoleDep();
        $this->permissionDep = new PermissionDep();
    }

    /**
     * ⭐ 权限计算唯一事实源
     * 返回：menus / router / buttonCodes
     */
    public function buildPermissionContextByUser($user): array
    {
        $role = $this->roleDep->find($user->role_id);
        $leafIds = json_decode($role->permission_id ?? '', true);

        if (empty($leafIds) || !is_array($leafIds)) {
            return [
                'permissions' => [],
                'router' => [],
                'buttonCodes' => [],
            ];
        }

        // 1. 获取全量权限数据 (建议Dep层做缓存)
        $allPerms = $this->permissionDep->getAllPermissions();
        $permMap  = array_column($allPerms, null, 'id');

        // 2. 高效计算有效权限ID (叶子节点向上回溯 + 路径缓存)
        $enabledIdMap = [];
        foreach ($leafIds as $leafId) {
            $curr = (int)$leafId;
            if (!isset($permMap[$curr])) continue;

            $path = [];
            $isValid = false;
            $visited = []; // 环检测

            while (isset($permMap[$curr])) {
                // 如果遇到已确认有效的节点，则当前路径剩余部分必然有效
                if (isset($enabledIdMap[$curr])) {
                    $isValid = true;
                    break;
                }
                
                // 环检测
                if (isset($visited[$curr])) break;
                $visited[$curr] = true;

                $path[] = $curr;
                $parentId = (int)$permMap[$curr]['parent_id'];

                if ($parentId === -1) {
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
        
        $enabledIds = array_keys($enabledIdMap);

        // 3. 分类收集数据
        $menusData = [];
        $routerData = [];
        $buttonCodes = [];

        foreach ($enabledIds as $id) {
            $p = $permMap[$id];
            
            // 按钮权限
            if ($p['type'] === PermissionEnum::TYPE_BUTTON && !empty($p['code'])) {
                $buttonCodes[] = $p['code'];
            }

            // 路由数据 (仅页面)
            if ($p['type'] == PermissionEnum::TYPE_PAGE && !empty($p['path']) && !empty($p['component'])) {
                 $routerData[] = [
                    'name' => 'menu_' . $p['id'],
                    'path' => $p['path'],
                    'component' => $p['component'],
                    'meta' => ['menuId' => (string)$p['id']],
                ];
            }

            // 菜单数据 (目录 + 页面)
            // 注：前端需要全量数据来处理面包屑和Tab显隐，这里不过滤 show_menu
            if (in_array($p['type'], [PermissionEnum::TYPE_DIR, PermissionEnum::TYPE_PAGE])) {
                $menusData[] = $p;
            }
        }

        // 修复：按 sort 字段排序，确保菜单顺序正确
        usort($menusData, function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        // 4. 构建树 (O(N)复杂度)
        $menus = $this->buildPermissionTree($menusData);

        return [
            'permissions'  => $menus,
            'router'       => $routerData,
            'buttonCodes'  => array_values(array_unique($buttonCodes)),
        ];
    }

    /**
     * O(N) 复杂度构建树
     */
    private function buildPermissionTree(array $items)
    {
        $tree = [];
        $map = [];

        // 初始化 Map
        foreach ($items as $item) {
            $map[$item['id']] = [
                'index'    => (string)$item['id'],
                'label'    => $item['name'],
                'path'     => $item['path'],
                'icon'     => $item['icon'],
                'children' => [],
                'i18n_key' => $item['i18n_key'] ?? '',
                'sort'     => (int)$item['sort'],
                'show_menu'=> isset($item['show_menu']) ? (int)$item['show_menu'] : CommonEnum::YES,
                'parent_id'=> (int)$item['parent_id'], // 用于后续挂载
            ];
        }

        // 组装树
        foreach ($map as $id => &$node) {
            $parentId = $node['parent_id'];
            if ($parentId === -1) {
                $tree[] = &$node;
            } else {
                if (isset($map[$parentId])) {
                    $map[$parentId]['children'][] = &$node;
                }
            }
        }

        return $tree;
    }
}

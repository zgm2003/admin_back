<?php

namespace app\service\User;

use app\dep\User\PermissionDep;
use app\dep\User\RoleDep;
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
        $role = $this->roleDep->first($user->role_id);
        $leafIds = json_decode($role->permission_id ?? '', true);

        if (empty($leafIds) || !is_array($leafIds)) {
            return [
                'permissions' => [],
                'router' => [],
                'buttonCodes' => [],
            ];
        }

        // 2️⃣ 所有“自身启用”的权限
        $allPerms = $this->permissionDep->getAllPermissions();
        $permMap  = array_column($allPerms, null, 'id');

        // 3️⃣ 向上补齐父链
        $includeSet = [];
        foreach ($leafIds as $leafId) {
            $cur = (int)$leafId;
            while (isset($permMap[$cur]) && !isset($includeSet[$cur])) {
                $includeSet[$cur] = true;
                $parent = (int)$permMap[$cur]['parent_id'];
                if ($parent === -1 || !isset($permMap[$parent])) {
                    break;
                }
                $cur = $parent;
            }
        }

        // 4️⃣ 父链完整性校验（强关联）
        $isChainEnabled = function (int $id) use ($permMap): bool {
            $cur = $id;
            while (true) {
                if (!isset($permMap[$cur])) return false;
                $parent = (int)$permMap[$cur]['parent_id'];
                if ($parent === -1) return true;
                if (!isset($permMap[$parent])) return false;
                $cur = $parent;
            }
        };

        $enabledIds = array_values(array_filter(
            array_keys($includeSet),
            fn($id) => $isChainEnabled((int)$id)
        ));

        // 5️⃣ 菜单树（目录 + 页面）
        // 过滤规则：
        // 1. 在 enabledIds 中（有权限）
        // 2. 类型是目录或页面
        // 3. show_menu 不等于 NO（即只保留显示或未设置的，CommonEnum::NO = 2）
        $menusData = array_filter($allPerms, fn($p) =>
            in_array($p['id'], $enabledIds, true) &&
            in_array($p['type'], [
                PermissionEnum::TYPE_DIR,
                PermissionEnum::TYPE_PAGE
            ]) &&
            (!isset($p['show_menu']) || (int)$p['show_menu'] !== CommonEnum::NO)
        );
        $menus = $this->buildPermissionTree($menusData, -1);
        
        // 6️⃣ 前端路由（仅页面）
        // 注意：路由不需要过滤 show_menu，隐藏菜单的页面依然需要注册路由
        $routerData = array_filter($allPerms, fn($p) =>
            in_array($p['id'], $enabledIds, true) &&
            $p['type'] == PermissionEnum::TYPE_PAGE
        );
        
        $router = [];
        foreach ($routerData as $m) {
            if (
                !empty($m['path']) &&
                !empty($m['component'])
            ) {
                $router[] = [
                    'name' => 'menu_' . $m['id'],
                    'path' => $m['path'],
                    'component' => $m['component'],
                    'meta' => [
                        'menuId' => (string)$m['id'],
                    ],
                ];
            }
        }

        // 7️⃣ 🔥 按钮权限（最终事实源）
        $buttonCodes = [];
        foreach ($enabledIds as $id) {
            if (
                isset($permMap[$id]) &&
                $permMap[$id]['type'] === PermissionEnum::TYPE_BUTTON &&
                !empty($permMap[$id]['code'])
            ) {
                $buttonCodes[] = $permMap[$id]['code'];
            }
        }

        return [
            'permissions'  => $menus,
            'router'       => $router,
            'buttonCodes'  => array_values(array_unique($buttonCodes)),
        ];
    }

    private function buildPermissionTree(array $items, $parentId)
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $children = $this->buildPermissionTree($items, $item['id']);
                $node     = [
                    'index'    => (string)$item['id'],
                    'label'    => $item['name'],
                    'path'     => $item['path'],
                    'icon'     => $item['icon'],
                    'children' => [],
                    'i18n_key' => isset($item['i18n_key']) ? $item['i18n_key'] : '',
                    'show_menu'=> isset($item['show_menu']) ? (int)$item['show_menu'] : CommonEnum::YES,
                ];
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }
        return $tree;
    }
}

<?php

namespace tests\Permission;

use app\dep\Permission\PermissionDep;
use app\service\Common\DictService;
use app\service\User\PermissionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PermissionCacheVersionContractTest extends TestCase
{
    public function testPermissionRouteCachesUsePostAppButtonMenuRemovalVersion(): void
    {
        self::assertSame(
            'perm_all_permissions_v20260426_remove_app_button_menu',
            PermissionDep::CACHE_KEY_ALL
        );

        $dictService = new ReflectionClass(DictService::class);

        self::assertSame(
            'dict_permission_tree_v20260426_remove_app_button_menu',
            $dictService->getConstant('CACHE_KEY_PERMISSION_TREE')
        );

        self::assertSame(
            'v20260426_remove_app_button_menu',
            PermissionService::BUTTON_CACHE_KEY_VERSION
        );

        self::assertSame(
            'auth_perm_uid_v20260426_remove_app_button_menu_12_app',
            PermissionService::buttonCacheKey(12, 'app')
        );
    }
}

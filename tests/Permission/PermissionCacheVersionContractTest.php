<?php

namespace tests\Permission;

use app\dep\Permission\PermissionDep;
use app\service\Common\DictService;
use app\service\User\PermissionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PermissionCacheVersionContractTest extends TestCase
{
    public function testPermissionDefinitionCachesUseStableSemanticKeys(): void
    {
        self::assertSame(
            'permission_all_active',
            PermissionDep::CACHE_KEY_ALL
        );

        $dictService = new ReflectionClass(DictService::class);

        self::assertSame(
            'dict_permission_tree',
            $dictService->getConstant('CACHE_KEY_PERMISSION_TREE')
        );
    }

    public function testUserPermissionCachesKeepExplicitVersionForIssuedSessions(): void
    {
        self::assertSame(
            'v20260426_rbac_page_grants',
            PermissionService::BUTTON_CACHE_KEY_VERSION
        );

        self::assertSame(
            'auth_perm_uid_v20260426_rbac_page_grants_12_app',
            PermissionService::buttonCacheKey(12, 'app')
        );
    }

    public function testPermissionTreeDictExposesTypeAndCodeForFrontendRbacEditors(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/service/Common/DictService.php');

        self::assertNotFalse($content);
        self::assertStringContainsString("'type'      => (int)", $content);
        self::assertStringContainsString("'code'      => \$item['code'] ?? ''", $content);
    }
}

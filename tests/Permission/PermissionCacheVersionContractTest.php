<?php

namespace tests\Permission;

use app\dep\Permission\PermissionDep;
use app\service\Common\DictService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PermissionCacheVersionContractTest extends TestCase
{
    public function testPermissionRouteCachesUsePostCodegenRemovalVersion(): void
    {
        self::assertSame(
            'perm_all_permissions_v20260426_remove_ai_codegen',
            PermissionDep::CACHE_KEY_ALL
        );

        $dictService = new ReflectionClass(DictService::class);

        self::assertSame(
            'dict_permission_tree_v20260426_remove_ai_codegen',
            $dictService->getConstant('CACHE_KEY_PERMISSION_TREE')
        );
    }
}

<?php

namespace tests\System;

use app\service\User\PermissionService;
use PHPUnit\Framework\TestCase;

class PermissionManagementContractTest extends TestCase
{
    public function testPermissionServiceBuildsTreeWithMatchedAncestors(): void
    {
        self::assertTrue(
            method_exists(PermissionService::class, 'buildTreeWithMatchedAncestors'),
            'PermissionService::buildTreeWithMatchedAncestors() should exist to keep matched children visible in tree filters.'
        );

        $items = [
            ['id' => 1, 'parent_id' => 0, 'name' => '系统管理', 'sort' => 1],
            ['id' => 2, 'parent_id' => 1, 'name' => '通知管理', 'sort' => 1],
            ['id' => 3, 'parent_id' => 0, 'name' => '通知中心', 'sort' => 2],
        ];

        $tree = PermissionService::buildTreeWithMatchedAncestors($items, [2]);

        self::assertCount(1, $tree);
        self::assertSame(1, $tree[0]['id']);
        self::assertArrayHasKey('children', $tree[0]);
        self::assertCount(1, $tree[0]['children']);
        self::assertSame(2, $tree[0]['children'][0]['id']);
    }

    public function testPermissionModuleUsesTargetedUserPermissionCacheInvalidation(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Permission/PermissionModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('clearUserPermissionCacheByPermissionIds', $content);
        self::assertStringContainsString('$this->clearUserPermissionCacheByPermissionIds([(int)$param[\'id\']]);', $content);
        self::assertStringContainsString('$this->clearUserPermissionCacheByPermissionIds($ids);', $content);
        self::assertStringContainsString("getRoleIdsByPermissionIds", $content);
    }

    public function testAuthPlatformModuleProtectsAdminPlatformFromDisableAndDelete(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Permission/AuthPlatformModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('核心平台 [admin] 不允许删除', $content);
        self::assertStringContainsString('核心平台 [admin] 不允许禁用', $content);
    }

    public function testRoleModuleListNormalizesPermissionIdsToPageAndButtonGrants(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Permission/RoleModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('normalizeAssignablePermissionIdsWithPageParents', $content);
    }

    public function testRolePermissionSyncKeepsPageAndButtonAssignments(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/dep/Permission/RolePermissionDep.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('filterActiveAssignablePermissionIds', $content);
        self::assertStringContainsString('normalizeAssignablePermissionIdsWithPageParents', $content);
        self::assertStringContainsString('PermissionEnum::TYPE_PAGE', $content);
        self::assertStringContainsString('PermissionEnum::TYPE_BUTTON', $content);
    }
}

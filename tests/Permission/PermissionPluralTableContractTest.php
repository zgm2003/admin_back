<?php

namespace tests\Permission;

use app\model\Permission\PermissionModel;
use app\model\Permission\RoleModel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PermissionPluralTableContractTest extends TestCase
{
    public function testPermissionAndRoleModelsUsePluralTables(): void
    {
        $permission = new ReflectionClass(PermissionModel::class);
        $role = new ReflectionClass(RoleModel::class);

        self::assertSame('permissions', $permission->getDefaultProperties()['table'] ?? null);
        self::assertSame('roles', $role->getDefaultProperties()['table'] ?? null);
    }

    public function testRenameMigrationRenamesOnlyEntityTablesAndKeepsPivotName(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database/migrations/20260426_rename_permission_role_tables.sql';
        self::assertFileExists($path);

        $sql = file_get_contents($path);
        self::assertNotFalse($sql);
        self::assertStringContainsString('RENAME TABLE `permission` TO `permissions`;', $sql);
        self::assertStringContainsString('RENAME TABLE `role` TO `roles`;', $sql);
        self::assertStringNotContainsString('RENAME TABLE `role_permissions`', $sql);
    }

    public function testRolePermissionReverseLookupIndexMigrationExists(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database/migrations/20260426_add_role_permission_lookup_index.sql';
        self::assertFileExists($path);

        $sql = file_get_contents($path);
        self::assertNotFalse($sql);
        self::assertStringContainsString('idx_role_permissions_permission_del_role', $sql);
        self::assertStringContainsString('`permission_id`, `is_del`, `role_id`', $sql);
    }
}

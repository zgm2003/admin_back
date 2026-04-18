<?php

namespace tests\System;

use PHPUnit\Framework\TestCase;

class RbacDataCleanupMigrationContractTest extends TestCase
{
    public function testRbacCleanupMigrationCoversLegacyNotificationsAndInvalidPermissionRefs(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database/migrations/20260418_rbac_data_cleanup.sql';
        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertStringContainsString("UPDATE notifications", $content);
        self::assertStringContainsString("/devTools/exportTask", $content);
        self::assertStringContainsString("/system/exportTask", $content);
        self::assertStringContainsString("UPDATE users_quick_entry", $content);
        self::assertStringContainsString("UPDATE role_permissions", $content);
    }
}

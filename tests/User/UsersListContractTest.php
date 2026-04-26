<?php

namespace tests\User;

use PHPUnit\Framework\TestCase;

class UsersListContractTest extends TestCase
{
    public function testUsersListControllerProtectsBatchEditAndExport(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/controller/User/UsersListController.php');

        self::assertNotFalse($content);
        self::assertMatchesRegularExpression('/@Permission\("user_userManager_batchEdit"\)\s*\*\/\s*public function batchEdit/s', $content);
        self::assertMatchesRegularExpression('/@Permission\("user_userManager_export"\)\s*\*\/\s*public function export/s', $content);
    }

    public function testUsersListEditIsTransactionalAndClearsPermissionCacheByPlatform(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/User/UsersListModule.php');

        self::assertNotFalse($content);
        self::assertMatchesRegularExpression('/public function edit\(\$request\): array\s*\{\s*.*withTransaction/s', $content);
        self::assertStringContainsString('$roleChanged = (int)$currentUser->role_id !== (int)$param[\'role_id\'];', $content);
        self::assertStringContainsString('if ($roleChanged)', $content);
        self::assertStringContainsString('AuthPlatformService::getAllowedPlatforms()', $content);
        self::assertStringContainsString('PermissionService::buttonCacheKey((int)$param[\'id\'], $platform)', $content);
    }

    public function testUsersListDepFiltersDeletedProfilesInJoin(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/dep/User/UsersDep.php');

        self::assertNotFalse($content);
        self::assertStringContainsString("where('up.is_del', CommonEnum::NO)", $content);
    }
}

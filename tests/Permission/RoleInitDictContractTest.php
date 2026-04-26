<?php

namespace tests\Permission;

use PHPUnit\Framework\TestCase;

class RoleInitDictContractTest extends TestCase
{
    public function testRoleInitProvidesPermissionTreeAndPlatformOptionsForMatrixEditor(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Permission/RoleModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('->setPermissionTree()', $content);
        self::assertStringContainsString('->setPermissionPlatformArr()', $content);
    }
}

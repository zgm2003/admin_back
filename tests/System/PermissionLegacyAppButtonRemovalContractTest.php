<?php

namespace tests\System;

use PHPUnit\Framework\TestCase;

class PermissionLegacyAppButtonRemovalContractTest extends TestCase
{
    public function testLegacyAppButtonControllerMethodsAndRoutesAreRemoved(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . DIRECTORY_SEPARATOR . 'app/controller/Permission/PermissionController.php');
        $routes = file_get_contents($root . DIRECTORY_SEPARATOR . 'routes/admin.php');

        self::assertNotFalse($controller);
        self::assertNotFalse($routes);

        foreach (['appButtonList', 'appButtonAdd', 'appButtonEdit', 'appButtonStatus', 'appButtonDel'] as $name) {
            self::assertStringNotContainsString($name, $controller);
            self::assertStringNotContainsString($name, $routes);
        }
    }

    public function testLegacyAppButtonModuleAndValidateMethodsAreRemoved(): void
    {
        $root = dirname(__DIR__, 2);
        $module = file_get_contents($root . DIRECTORY_SEPARATOR . 'app/module/Permission/PermissionModule.php');
        $validate = file_get_contents($root . DIRECTORY_SEPARATOR . 'app/validate/Permission/PermissionValidate.php');

        self::assertNotFalse($module);
        self::assertNotFalse($validate);

        foreach (['appButtonList', 'appButtonAdd', 'appButtonEdit'] as $name) {
            self::assertStringNotContainsString('function ' . $name, $module);
            self::assertStringNotContainsString('function ' . $name, $validate);
        }
    }
}

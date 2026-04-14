<?php

namespace tests\User;

use app\validate\User\UsersQuickEntryValidate;
use PHPUnit\Framework\TestCase;

class UsersQuickEntryContractTest extends TestCase
{
    public function testQuickEntryValidatorExposesSaveOnly(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/validate/User/UsersQuickEntryValidate.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('public static function save()', $content);
        self::assertStringNotContainsString('public static function add()', $content);
        self::assertStringNotContainsString('public static function del()', $content);
        self::assertStringNotContainsString('public static function sort()', $content);

        $rules = UsersQuickEntryValidate::save();
        self::assertArrayHasKey('permission_ids', $rules);
    }

    public function testQuickEntryModuleExposesAtomicSaveOnly(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/User/UsersQuickEntryModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('public function save', $content);
        self::assertStringContainsString('withTransaction', $content);
        self::assertStringNotContainsString('public function add', $content);
        self::assertStringNotContainsString('public function del', $content);
        self::assertStringNotContainsString('public function sort', $content);
    }

    public function testQuickEntryControllerAndRouteExposeSaveOnly(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/controller/User/UsersQuickEntryController.php');
        $routes = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes/admin.php');

        self::assertNotFalse($controller);
        self::assertNotFalse($routes);
        self::assertStringContainsString('public function save', $controller);
        self::assertStringNotContainsString('public function add', $controller);
        self::assertStringNotContainsString('public function del', $controller);
        self::assertStringNotContainsString('public function sort', $controller);
        self::assertStringContainsString('/UsersQuickEntry/save', $routes);
        self::assertStringNotContainsString('/UsersQuickEntry/add', $routes);
        self::assertStringNotContainsString('/UsersQuickEntry/del', $routes);
        self::assertStringNotContainsString('/UsersQuickEntry/sort', $routes);
    }
}

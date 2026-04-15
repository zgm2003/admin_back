<?php

namespace tests\User;

use PHPUnit\Framework\TestCase;

class AuthContractTest extends TestCase
{
    public function testPublicAuthRouteAndControllerNoLongerExposeRegister(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes/api.php');
        $controller = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/controller/User/UsersController.php');
        $validate = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/validate/User/UsersValidate.php');
        $emailEnum = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/enum/EmailEnum.php');

        self::assertNotFalse($routes);
        self::assertNotFalse($controller);
        self::assertNotFalse($validate);
        self::assertNotFalse($emailEnum);
        self::assertStringNotContainsString('/Users/register', $routes);
        self::assertStringNotContainsString('public function register', $controller);
        self::assertStringNotContainsString('public static function register', $validate);
        self::assertStringNotContainsString('SCENE_REGISTER', $validate);
        self::assertStringNotContainsString('SCENE_REGISTER', $emailEnum);
        self::assertStringNotContainsString("'register'", $emailEnum);
        self::assertStringContainsString('public static function getTheme', $emailEnum);
        self::assertStringContainsString('/Users/login', $routes);
        self::assertStringContainsString('public function login', $controller);
    }

    public function testAutoRegisterCreatesProfileInsideTransaction(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/User/AuthModule.php');
        $normalized = str_replace(["\r", "\n", "\t", ' '], '', (string)$content);

        self::assertNotFalse($content);
        self::assertStringContainsString('function autoRegister', $content);
        self::assertStringContainsString('withTransaction', $content);
        self::assertStringContainsString('UsersDep::class)->add([', $normalized);
        self::assertStringContainsString('UserProfileDep::class)->add([', $normalized);
    }
}

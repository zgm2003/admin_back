<?php

namespace tests\Ai;

use app\enum\AiEnum;
use app\enum\CineEnum;
use PHPUnit\Framework\TestCase;

class CineFactoryContractTest extends TestCase
{
    public function testCineSceneAndStatusesAreRegistered(): void
    {
        self::assertSame('cine_project', AiEnum::SCENE_CINE_PROJECT);
        self::assertArrayHasKey(AiEnum::SCENE_CINE_PROJECT, AiEnum::$sceneArr);
        self::assertSame('AI短剧工厂', AiEnum::$sceneArr[AiEnum::SCENE_CINE_PROJECT]);

        self::assertSame(1, CineEnum::STATUS_DRAFT);
        self::assertSame(2, CineEnum::STATUS_GENERATING);
        self::assertSame(3, CineEnum::STATUS_READY);
        self::assertSame(4, CineEnum::STATUS_IMAGE_GENERATING);
        self::assertSame(5, CineEnum::STATUS_COMPLETED);
        self::assertSame(6, CineEnum::STATUS_FAILED);
    }

    public function testCineRoutesAreRegisteredUnderAdminApi(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes/admin.php');

        self::assertNotFalse($routes);
        foreach (['init', 'statusCount', 'list', 'add', 'edit', 'del', 'generate', 'generateStoryboard', 'generateKeyframes'] as $action) {
            self::assertStringContainsString("Route::post('/Cine/{$action}'", $routes);
        }
    }

    public function testCineMigrationCreatesTablesMenuAndDefaultRolePermission(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database/migrations/20260430_add_ai_cine_factory.sql';
        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `cine_projects`', $content);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `cine_assets`', $content);
        self::assertStringContainsString('`is_del` tinyint UNSIGNED NOT NULL DEFAULT 2', $content);
        self::assertStringContainsString('`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', $content);
        self::assertStringContainsString('`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $content);
        self::assertStringContainsString('menu.ai_cine', $content);
        self::assertStringContainsString('/ai/cine', $content);
        self::assertStringContainsString('ai/cine', $content);
        self::assertStringContainsString('INSERT INTO `role_permissions`', $content);
        self::assertStringContainsString('AI短剧分镜图片生成', $content);
        self::assertStringContainsString('gpt-image-2', $content);
    }

    public function testCineDraftQueueUsesStreamingTextGeneration(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/queue/redis/slow/CineProcess.php';
        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertStringContainsString('AiChatService::chatStream', $content);
        self::assertStringContainsString("'stream' => true", $content);
        self::assertStringContainsString("'disable_tools' => true", $content);
        self::assertStringContainsString("'max_output_tokens' => 6000", $content);
        self::assertStringContainsString('模型未返回可用分镜', $content);
    }
}

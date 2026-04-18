<?php

namespace tests\System;

use PHPUnit\Framework\TestCase;

class CoreCronEnableMigrationContractTest extends TestCase
{
    public function testCoreCronMigrationEnablesAiTimeoutAndNotificationScheduler(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database/migrations/20260418_enable_core_cron_tasks.sql';
        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertStringContainsString("UPDATE cron_task", $content);
        self::assertStringContainsString("ai_run_timeout", $content);
        self::assertStringContainsString("notification_task_scheduler", $content);
        self::assertStringContainsString("status = 1", $content);
    }
}

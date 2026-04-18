<?php

namespace tests\System;

use PHPUnit\Framework\TestCase;

class ChatSampleCleanupMigrationContractTest extends TestCase
{
    public function testChatSampleCleanupMigrationSoftDeletesChatDomainTables(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database/migrations/20260418_chat_sample_cleanup.sql';
        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertStringContainsString('UPDATE chat_messages', $content);
        self::assertStringContainsString('UPDATE chat_participants', $content);
        self::assertStringContainsString('UPDATE chat_conversations', $content);
        self::assertStringContainsString('UPDATE chat_contacts', $content);
        self::assertStringContainsString('is_del = 1', $content);
    }
}

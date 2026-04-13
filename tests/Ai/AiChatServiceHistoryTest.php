<?php

namespace tests\Ai;

use app\service\Ai\AiChatService;
use PHPUnit\Framework\TestCase;

class AiChatServiceHistoryTest extends TestCase
{
    public function testNormalizeHistoryMessagesKeepsValidAlternatingPairs(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'u1'],
            ['role' => 'assistant', 'content' => 'a1'],
            ['role' => 'user', 'content' => 'u2'],
            ['role' => 'assistant', 'content' => 'a2'],
        ];

        self::assertSame($messages, AiChatService::normalizeHistoryMessages($messages));
    }

    public function testNormalizeHistoryMessagesDropsTrailingUnansweredUser(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'u1'],
            ['role' => 'assistant', 'content' => 'a1'],
            ['role' => 'user', 'content' => 'u2'],
        ];

        self::assertSame([
            ['role' => 'user', 'content' => 'u1'],
            ['role' => 'assistant', 'content' => 'a1'],
        ], AiChatService::normalizeHistoryMessages($messages));
    }

    public function testNormalizeHistoryMessagesCollapsesConsecutiveUsersBeforeAssistant(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'stale'],
            ['role' => 'user', 'content' => 'latest'],
            ['role' => 'assistant', 'content' => 'answer'],
        ];

        self::assertSame([
            ['role' => 'user', 'content' => 'latest'],
            ['role' => 'assistant', 'content' => 'answer'],
        ], AiChatService::normalizeHistoryMessages($messages));
    }
}

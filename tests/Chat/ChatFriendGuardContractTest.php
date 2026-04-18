<?php

namespace tests\Chat;

use PHPUnit\Framework\TestCase;

class ChatFriendGuardContractTest extends TestCase
{
    public function testChatContactDepExposesConfirmedContactUserIdsHelper(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/dep/Chat/ChatContactDep.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('public function getConfirmedContactUserIds', $content);
    }

    public function testCreateGroupRequiresConfirmedContacts(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Chat/ChatConversationModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('ensureConfirmedContacts', $content);
        self::assertStringContainsString('群聊成员必须是已确认的好友', $content);
    }

    public function testGroupInviteRequiresConfirmedContacts(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Chat/ChatGroupModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('ensureConfirmedContacts', $content);
        self::assertStringContainsString('仅可邀请已确认的好友加入群聊', $content);
    }
}

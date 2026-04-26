<?php

namespace tests\System;

use app\validate\Chat\ChatValidate;
use app\validate\System\NotificationValidate;
use app\validate\System\OperationLogValidate;
use app\validate\User\UsersLoginLogValidate;
use PHPUnit\Framework\TestCase;

class UnifiedPagePaginationContractTest extends TestCase
{
    public function testListValidatorsExposeUnifiedPageFields(): void
    {
        $cases = [
            'NotificationValidate::pageList' => NotificationValidate::pageList(),
            'ChatValidate::messageList' => ChatValidate::messageList(),
            'OperationLogValidate::list' => OperationLogValidate::list(),
            'UsersLoginLogValidate::list' => UsersLoginLogValidate::list(),
        ];

        foreach ($cases as $label => $rules) {
            self::assertArrayHasKey('current_page', $rules, $label . ' must accept current_page');
            self::assertArrayHasKey('page_size', $rules, $label . ' must accept page_size');
        }
    }

    public function testAppNotificationRouteUsesRegularListAction(): void
    {
        $routes = $this->read('routes/app.php');

        self::assertStringContainsString("/Notification/list', [controller\\System\\NotificationController::class, 'list']", $routes);
    }

    public function testChatMessageListReturnsRegularPagination(): void
    {
        $module = $this->read('app/module/Chat/ChatMessageModule.php');

        self::assertStringContainsString('return self::paginate($list, [', $module);
        self::assertStringContainsString('\'current_page\' => $paginator->currentPage()', $module);
        self::assertStringContainsString('\'total\'        => $paginator->total()', $module);
    }

    private function read(string $relativePath): string
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath);

        self::assertNotFalse($content, 'Failed to read ' . $relativePath);

        return $content;
    }
}

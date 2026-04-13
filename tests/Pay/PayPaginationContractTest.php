<?php

namespace tests\Pay;

use app\validate\Pay\OrderValidate;
use app\validate\Pay\PayChannelValidate;
use app\validate\Pay\PayNotifyLogValidate;
use app\validate\Pay\PayReconcileValidate;
use app\validate\Pay\PayTransactionValidate;
use app\validate\Pay\UserWalletValidate;
use PHPUnit\Framework\TestCase;

class PayPaginationContractTest extends TestCase
{
    public function testAdminPayListValidatorsUseCurrentPage(): void
    {
        $cases = [
            ['label' => 'PayChannelValidate::list', 'rules' => PayChannelValidate::list()],
            ['label' => 'OrderValidate::list', 'rules' => OrderValidate::list()],
            ['label' => 'UserWalletValidate::list', 'rules' => UserWalletValidate::list()],
            ['label' => 'UserWalletValidate::transactions', 'rules' => UserWalletValidate::transactions()],
            ['label' => 'PayTransactionValidate::list', 'rules' => PayTransactionValidate::list()],
            ['label' => 'PayReconcileValidate::list', 'rules' => PayReconcileValidate::list()],
            ['label' => 'PayNotifyLogValidate::list', 'rules' => PayNotifyLogValidate::list()],
        ];

        foreach ($cases as $case) {
            self::assertArrayHasKey('current_page', $case['rules'], $case['label'] . ' must accept current_page');
            self::assertArrayNotHasKey('page', $case['rules'], $case['label'] . ' should not accept legacy page');
        }
    }

    public function testAdminPayDepsReadCurrentPageInsteadOfLegacyPage(): void
    {
        $files = [
            'app/dep/Pay/PayChannelDep.php',
            'app/dep/Pay/OrderDep.php',
            'app/dep/Pay/UserWalletDep.php',
            'app/dep/Pay/WalletTransactionDep.php',
            'app/dep/Pay/PayTransactionDep.php',
            'app/dep/Pay/PayReconcileTaskDep.php',
            'app/dep/Pay/PayNotifyLogDep.php',
            'app/module/Pay/WalletQueryModule.php',
            'app/module/Pay/RechargeModule.php',
        ];

        foreach ($files as $file) {
            $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $file);

            self::assertNotFalse($content, 'Failed to read ' . $file);
            self::assertStringContainsString("\$param['current_page']", $content, $file . ' should read current_page');
            self::assertStringNotContainsString("\$param['page']", $content, $file . ' still reads legacy page');
        }
    }
}

<?php

namespace tests\Pay;

use PHPUnit\Framework\TestCase;

class WalletAndExportContractTest extends TestCase
{
    public function testWalletInfoReflectsWhetherWalletExists(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Pay/WalletQueryModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString("'wallet_exists' => \$wallet ? CommonEnum::YES : CommonEnum::NO", $content);
    }

    public function testExportTaskModuleCleansExpiredTasksBeforeListingAndCounting(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/System/ExportTaskModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('cleanExpired();', $content);
    }
}

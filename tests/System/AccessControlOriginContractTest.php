<?php

namespace tests\System;

use app\middleware\AccessControl;
use PHPUnit\Framework\TestCase;

class AccessControlOriginContractTest extends TestCase
{
    public function testAllowsChromeExtensionOriginsForPluginPopupRequests(): void
    {
        $origin = 'chrome-extension://abcdefghijklmnopabcdefghijklmnop';

        self::assertSame($origin, AccessControl::resolveAllowedOrigin($origin));
    }

    public function testAllowsConfiguredWwwProductionOrigin(): void
    {
        self::assertContains('https://www.zgm2003.cn', AccessControl::allowedOrigins());
    }
}
